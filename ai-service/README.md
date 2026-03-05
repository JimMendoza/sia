# AI Service (RAG + LLM Providers)

FastAPI service for semantic retrieval (`/retrieve`), node resolution (`/resolve/nodes`), node-aware chat (`/chat`), reindex (`/reindex`, `/reindex/node`) and Excel ingestion (`/ingest/excel`).

## Run

```bash
cd ai-service
python -m venv .venv
. .venv/Scripts/Activate.ps1
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
```

If `llama-cpp-python` fails on Python 3.13 in Windows (wheel unavailable), use Python 3.12:

```bash
deactivate
Remove-Item -Recurse -Force .venv
py -3.12 -m venv .venv
. .venv/Scripts/Activate.ps1
pip install -r requirements.txt
```

## Ollama setup (recommended in dev)

Install Ollama:

```bash
winget install Ollama.Ollama
```

Pull model:

```bash
ollama pull phi3
```

Ensure Ollama API is running at `http://127.0.0.1:11434`.

## Environment variables

Provider selector:

- `LLM_PROVIDER` default: `ollama` (`ollama` | `llamacpp`)

Ollama:

- `OLLAMA_URL` default: `http://127.0.0.1:11434`
- `OLLAMA_MODEL` default: `phi3`

Shared generation params:

- `LLM_MAX_TOKENS` default: `256`
- `LLM_TEMPERATURE` default: `0.2`

llama.cpp compatibility mode:

- `LLM_MODEL_PATH` (local path to `.gguf`; preferred if already downloaded)
- `LLM_MODEL_REPO` (default: `TheBloke/TinyLlama-1.1B-Chat-v1.0-GGUF`)
- `LLM_MODEL_FILE` (default: `tinyllama-1.1b-chat-v1.0.Q4_K_M.gguf`)
- `LLM_CTX` (default: `2048`)

## Endpoints

- `GET /health`
- `POST /retrieve`
- `POST /resolve/nodes`
- `POST /chat`
- `POST /reindex`
- `POST /reindex/node`
- `POST /ingest/excel`

## Ingest Excel (dev)

Allowed base directory for Excel path:

- `C:\laragon\www\gore-chatbot-api\storage\app\kb\`

Request:

```json
{
  "path": "C:\\laragon\\www\\gore-chatbot-api\\storage\\app\\kb\\kb_demo_tupa_rof.xlsx",
  "mode": "upsert",
  "embed": true
}
```

Cabeceras obligatorias y definitivas:

- `DOCUMENTOS`
  `document_key,title,doc_type,source_url,status,version,issued_date,entity,notes`
- `TUPA_TRAMITES`
  `document_key,procedure_code,procedure_title,descripcion_procedimiento,requisitos,formularios,canales_atencion,pago_tramite,modalidad_pago,plazo,calificacion_procedimiento,sedes_horarios_atencion,unidad_org_presenta_documentacion,unidad_org_responsable_aprobar,consulta_procedimiento,instancias_resolucion_recursos,base_legal,pdf_page_start,pdf_page_end,status`
- `ROF_UNIDADES`
  `document_key,org_code,org_title,dependencia_superior,funciones,atribuciones,canales_contacto,base_legal,pdf_page_start,pdf_page_end,status,tags`

Notas:

- El servicio valida cabeceras estrictas (sin aliases).
- `DOCUMENTOS` es obligatorio.
- Puedes enviar un Excel con solo TUPA o solo ROF.
- Al menos una hoja de contenido debe tener filas: `TUPA_TRAMITES` o `ROF_UNIDADES`.
- Si faltan cabeceras o hay cabeceras no permitidas en una hoja enviada, retorna error en `errors[]`.

## Publish KB documents and nodes

Only documents with `status = 'published'` are used by `/retrieve` and `/chat`.
If you retrieve with `node_id`, that node must also be `published`.

Example:

```sql
UPDATE kb_documents
SET status = 'published'
WHERE id = '11111111-1111-1111-1111-111111111111';

UPDATE kb_nodes
SET status = 'published'
WHERE document_id = '11111111-1111-1111-1111-111111111111';
```

Example `/chat` request:

```json
{
  "message": "Cual es el plazo de atencion?",
  "top_k": 5
}
```

## Retrieval filters

`/retrieve` accepts optional filters:

```json
{
  "query": "Acceso a la Informacion Publica",
  "top_k": 5,
  "filters": {
    "document_id": "uuid-opcional",
    "procedure_title": "Acceso a la Informacion Publica y orientacion al administrado",
    "node_id": "uuid-opcional"
  }
}
```

## Reindex by node

Use `/reindex/node` to regenerate embeddings only for chunks of one node.

```json
{
  "node_id": "44444444-4444-4444-4444-444444444441",
  "force": true
}
```
