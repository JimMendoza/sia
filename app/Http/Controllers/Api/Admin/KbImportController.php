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
        $absolutePath = '';

        try {
            $disk->makeDirectory($directory);
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'xlsx');
            $filename = now()->format('Ymd_His').'_'.Str::random(16).'.'.$extension;
            $relativePath = $disk->putFileAs($directory, $file, $filename);
            $absolutePath = storage_path('app/'.$directory.'/'.$filename);

            logger()->info('KB Excel import stored file', [
                'relativePath' => $relativePath,
                'absolutePath' => $absolutePath,
                'exists' => is_file($absolutePath),
                'size' => is_file($absolutePath) ? filesize($absolutePath) : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            logger()->error('KB Excel import store failed', [
                'absolutePath' => $absolutePath,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'documents_upserted' => 0,
                'nodes_upserted' => 0,
                'chunks_upserted' => 0,
                'embedded' => 0,
                'errors' => [
                    "No se pudo guardar el archivo Excel: {$absolutePath}",
                    $exception->getMessage(),
                ],
            ], 422);
        }

        $savedSize = is_file($absolutePath) ? (int) filesize($absolutePath) : 0;
        if (! is_file($absolutePath) || $savedSize <= 0) {
            logger()->error('KB Excel import stored file validation failed', [
                'absolutePath' => $absolutePath,
                'exists' => is_file($absolutePath),
                'size' => $savedSize,
            ]);

            return response()->json([
                'documents_upserted' => 0,
                'nodes_upserted' => 0,
                'chunks_upserted' => 0,
                'embedded' => 0,
                'errors' => [
                    "No se pudo guardar el archivo Excel: {$absolutePath}",
                    'Archivo inexistente o vacio despues de guardado.',
                ],
            ], 422);
        }

        try {
            $result = $aiClient->ingestExcel(
                path: $absolutePath,
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
