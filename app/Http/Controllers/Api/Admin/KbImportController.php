<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportKbExcelRequest;
use App\Services\Chatbot\AiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class KbImportController extends Controller
{
    public function importExcel(
        ImportKbExcelRequest $request,
        AiClient $aiClient,
    ): JsonResponse {
        $hasFile = $request->hasFile('file');
        $file = $request->file('file');
        $mode = (string) $request->validated('mode', 'upsert');
        $embed = (bool) $request->boolean('embed', true);

        $uploadContext = [
            'hasFile' => $hasFile,
            'isUploadedFile' => $file instanceof UploadedFile,
            'originalName' => $file instanceof UploadedFile ? $file->getClientOriginalName() : null,
            'mimeType' => $file instanceof UploadedFile ? $file->getClientMimeType() : null,
            'size' => $file instanceof UploadedFile ? $file->getSize() : null,
            'isValid' => $file instanceof UploadedFile ? $file->isValid() : false,
            'error' => $file instanceof UploadedFile ? $file->getError() : null,
            'errorMessage' => $file instanceof UploadedFile ? $file->getErrorMessage() : null,
            'tmpPath' => $file instanceof UploadedFile ? $file->getPathname() : null,
            'realPath' => $file instanceof UploadedFile ? $file->getRealPath() : null,
        ];

        logger()->info('KB Excel import upload received', $uploadContext);

        if (! $hasFile || ! ($file instanceof UploadedFile) || ! $file->isValid()) {
            return response()->json([
                'documents_upserted' => 0,
                'nodes_upserted' => 0,
                'chunks_upserted' => 0,
                'embedded' => 0,
                'errors' => [
                    'Archivo Excel no valido.',
                    (string) ($uploadContext['errorMessage'] ?? 'No upload error detail available.'),
                ],
            ], 422);
        }

        $disk = Storage::disk('local');
        $directory = 'kb/imports';
        $relativePath = null;
        $diskAbsolutePath = null;
        $aiAbsolutePath = null;

        try {
            $disk->makeDirectory($directory);
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'xlsx');
            $filename = now()->format('Ymd_His').'_'.Str::random(16).'.'.$extension;
            $relativePath = $file->storeAs($directory, $filename, 'local');

            if (! $relativePath) {
                logger()->error('KB Excel import storeAs failed', [
                    'directory' => $directory,
                    'filename' => $filename,
                    'disk' => 'local',
                ]);

                return response()->json([
                    'documents_upserted' => 0,
                    'nodes_upserted' => 0,
                    'chunks_upserted' => 0,
                    'embedded' => 0,
                    'errors' => ['storeAs devolvio false al guardar el archivo Excel.'],
                ], 422);
            }

            $diskAbsolutePath = $disk->path($relativePath);
            $diskAbsolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $diskAbsolutePath);
            $diskAbsolutePath = realpath($diskAbsolutePath) ?: $diskAbsolutePath;

            $expectedAiPath = storage_path('app'.DIRECTORY_SEPARATOR.$relativePath);
            $expectedAiPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $expectedAiPath);
            if (! is_dir(dirname($expectedAiPath))) {
                if (! mkdir(dirname($expectedAiPath), 0777, true) && ! is_dir(dirname($expectedAiPath))) {
                    throw new \RuntimeException('No se pudo crear el directorio destino para Excel import.');
                }
            }

            $sourceComparisonPath = DIRECTORY_SEPARATOR === '\\' ? strtolower($diskAbsolutePath) : $diskAbsolutePath;
            $targetComparisonPath = DIRECTORY_SEPARATOR === '\\' ? strtolower($expectedAiPath) : $expectedAiPath;

            if ($sourceComparisonPath !== $targetComparisonPath) {
                if (! @copy($diskAbsolutePath, $expectedAiPath)) {
                    throw new \RuntimeException(
                        "No se pudo copiar el Excel hacia path AI permitido. source={$diskAbsolutePath}; target={$expectedAiPath}"
                    );
                }
            }

            $aiAbsolutePath = realpath($expectedAiPath) ?: $expectedAiPath;
            clearstatcache(true, $aiAbsolutePath);
            $exists = is_file($aiAbsolutePath);
            $savedSize = $exists ? (int) filesize($aiAbsolutePath) : 0;

            logger()->info('KB Excel import stored file', [
                'relativePath' => $relativePath,
                'diskAbsolutePath' => $diskAbsolutePath,
                'aiAbsolutePath' => $aiAbsolutePath,
                'exists' => $exists,
                'size' => $savedSize,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            logger()->error('KB Excel import store failed', [
                'relativePath' => $relativePath,
                'diskAbsolutePath' => $diskAbsolutePath,
                'aiAbsolutePath' => $aiAbsolutePath,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'documents_upserted' => 0,
                'nodes_upserted' => 0,
                'chunks_upserted' => 0,
                'embedded' => 0,
                'errors' => [
                    'No se pudo guardar el archivo Excel.',
                    $exception->getMessage(),
                ],
            ], 422);
        }

        $aiAbsolutePath = (string) $aiAbsolutePath;
        clearstatcache(true, $aiAbsolutePath);
        $savedSize = is_file($aiAbsolutePath) ? (int) filesize($aiAbsolutePath) : 0;
        if (! is_file($aiAbsolutePath) || $savedSize <= 0) {
            logger()->error('KB Excel import stored file validation failed', [
                'relativePath' => $relativePath,
                'diskAbsolutePath' => $diskAbsolutePath,
                'aiAbsolutePath' => $aiAbsolutePath,
                'exists' => is_file($aiAbsolutePath),
                'size' => $savedSize,
            ]);

            return response()->json([
                'documents_upserted' => 0,
                'nodes_upserted' => 0,
                'chunks_upserted' => 0,
                'embedded' => 0,
                'errors' => [
                    "No se pudo guardar el archivo Excel: {$aiAbsolutePath}",
                    'Archivo inexistente o vacio despues de guardado.',
                ],
            ], 422);
        }

        logger()->info('KB Excel import AI path ready', [
            'absPath' => $aiAbsolutePath,
            'size' => $savedSize,
        ]);

        try {
            $result = $aiClient->ingestExcel(
                path: $aiAbsolutePath,
                mode: $mode,
                embed: $embed,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'documents_upserted' => 0,
                'nodes_upserted' => 0,
                'chunks_upserted' => 0,
                'embedded' => 0,
                'errors' => [$exception->getMessage()],
            ], 422);
        }

        return response()->json([
            'documents_upserted' => (int) ($result['documents_upserted'] ?? 0),
            'nodes_upserted' => (int) ($result['nodes_upserted'] ?? 0),
            'chunks_upserted' => (int) ($result['chunks_upserted'] ?? 0),
            'embedded' => (int) ($result['embedded'] ?? 0),
            'errors' => array_values((array) ($result['errors'] ?? [])),
        ]);
    }
}
