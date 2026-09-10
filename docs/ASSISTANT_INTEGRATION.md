# Asisten Orchestrator × SIUPK

SIUPK = **Tenant Adapter** (RBAC + domain tools).  
Orchestrator (repo terpisah) = brain + chat SSE + RAG FAQ + tool loop.

## Alur

```
User login SIUPK
  → FAB Ariel (AssistantWidget)
  → GET /api/assistant/session-token   (web session + tenant + assistant.use)
  → Orchestrator mint session (Bearer shared secret, server-side only)
  → Browser POST {orchestrator}/api/v1/chat + SSE (session_token)
  → tool call
  → POST /api/assistant/tools/{tool}  (HMAC)
       body: { tool, external_user_id, params, ts }
  → SIUPK: verifikasi sig → resolve user → permission → Domain service
```

`external_user_id` = `users.row_id` (platform).

## Env SIUPK

```env
ASSISTANT_ORCHESTRATOR_BASE_URL=http://host.docker.internal:8100
ASSISTANT_ORCHESTRATOR_PUBLIC_URL=http://localhost:8100
ASSISTANT_SHARED_SECRET=tk_...   # same secret orchestrator uses
ASSISTANT_WIDGET_ENABLED=true
# multi-tenant satu host:
TENANCY_ALLOW_HEADER=true
```

## RBAC

- Katalog: `config/permissions.php`
- User **tanpa** role → full access (legacy)
- User **dengan** role → union permission role packs
- Superadmin → full
- Tool map: `permissions.tool_map.*`
- Gate chat: `assistant.use`

## Endpoint tool

| Tool | Permission | Write? |
|---|---|---|
| `search_members` | members.view | no |
| `search_groups` | groups.view | no |
| `search_loans` | loans.view | no |
| `get_loan` | loans.view | no |
| `list_accounts` | journals.view | no |
| `search_journals` | journals.view | no |
| `search_assets` | journals.view | no |
| `get_asset` | journals.view | no |
| `list_due_billing` | messages.send | no |
| `create_journal_entry` | journals.create | **yes** |
| `reverse_journal` | journals.create | **yes** |
| `record_installment` | installments.record | **yes** |
| `send_billing_notices` | messages.send | **yes** |

### Reaktif (bukan form flat)

1. NL → tool read (`search_*` / `list_accounts` / `search_journals`)  
2. `match_count === 1` → pakai  
3. `needs_clarification` / `match_count ≠ 1` → tampilkan candidates — **jangan tebak**  
4. Write tool **tanpa** `confirm` → `preview: true`  
5. User setuju → panggil ulang `proposed_params` + `confirm: true`

### Preview write

```json
{
  "preview": true,
  "needs_confirmation": true,
  "action": "record_installment",
  "summary": "…",
  "plan": { },
  "warnings": [],
  "options": [],
  "proposed_params": { "confirm": true }
}
```

Lookup: `{ "items": [...], "match_count": 2, "needs_clarification": true }`

### HMAC tool headers (orchestrator → SIUPK)

```
secret = sha256(ASSISTANT_SHARED_SECRET)
sig    = HMAC-SHA256(ts + '.' + rawBody, secret)

X-Orchestrator-Signature
X-Orchestrator-Timestamp   (ms)
X-Orchestrator-Key-Hash    (optional = secret hash)
X-Tenant-Code              (optional)
```

Alias accepted: `X-Assistant-*`.

Base URL tools:

```
{APP_URL}/api/assistant/tools/{tool_name}
POST {APP_URL}/api/assistant/tools   body.tool = "..."
```

Dump schema seed orchestrator:

```
php artisan siupk:assistant-tools --base=http://host.docker.internal:8080
```

## Session (browser)

`GET /api/assistant/session-token` (auth + tenant) →  
`{ session_token, expires_at, endpoint, conversation_id? }`

Browser chat ke orchestrator dengan `Authorization: Bearer {session_token}` only — **never** shared secret.

## SSE events (orchestrator → widget)

`conversation` | `text` | `tool_use` | `tool_result` | `confirmation_required` | `error` | `result`

## Keamanan

- Shared secret **tidak** ke browser; hanya session_token short-lived
- Tool call ditandatangani HMAC
- Aksi dijalankan sebagai user pemberi perintah
- Posted journal immutable di domain layer

## Cutover dari encompletion

Encompletion client/widget/HMAC brand **dihapus**.  
Lihat repo orchestrator + `docs/ai-assistant-project-guide.md` untuk brain/RAG/admin.
