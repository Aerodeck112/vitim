# VITIM AI — API intern v1

> Reflectă `cloud/routes/web.php` la versiunea **0.2.0**. Folosit de dashboard și de integrări interne.
> API-ul public pentru widget și pentru pluginul WordPress (chei de site, semnături HMAC) vine în Fazele 4–5. Logica de verificare a cheilor există deja (`SiteKeyService`), dar endpoint-urile nu sunt încă expuse.

## Convenții

| | |
|---|---|
| Bază | `/api/v1` |
| Autentificare | Sesiune (login + 2FA, unde e activ). Cererile care modifică date trimit tokenul CSRF (`X-XSRF-TOKEN` / `_token`). Tokenuri API pentru terți: neimplementate |
| Firmă | Rutele de firmă au prefixul `/api/v1/orgs/{slug}`. Firma vine din URL și e validată pe server (membru sau echipa VITIM). Toate interogările sunt filtrate automat pe ea |
| Permisiuni | Fiecare rută cere o permisiune (`can:`), vezi tabelul de mai jos. Matricea rol → permisiuni: `App\Enums\OrgRole` |
| Format | JSON. Datele sunt în `data`; listele au în plus `links` și `meta` (paginare Laravel) |
| Paginare | `?page=N&per_page=1..100` (implicit 25) |
| Limită | 120 de cereri pe minut per utilizator → `429 rate_limited` |
| Log | Un rând per cerere: metodă, rută, status, durată, utilizator, firmă. Fără corpul cererii |

### Erori

```json
{ "error": { "code": "validation_failed", "message": "Datele trimise nu sunt valide.", "details": { "email": ["..."] } } }
```

| HTTP | `code` | Când |
|---|---|---|
| 401 | `unauthenticated` | Fără sesiune |
| 403 | `forbidden` | Rolul nu are permisiunea; echipa VITIM fără 2FA în sesiune |
| 404 | `not_found` | Resursa nu există **sau aparține altei firme**; firma din URL nu e a utilizatorului |
| 409 | `duplicate_contact` | Email / telefon / ID extern deja folosit în firmă (`details.existing_contact_id`) |
| 419 | `csrf_token_mismatch` | Token CSRF lipsă sau expirat |
| 422 | `validation_failed` | Validare (`details` pe câmpuri) |
| 429 | `rate_limited` | Prea multe cereri |
| 500 | `server_error` | Neprevăzut. Fără detalii interne în producție |

Accesul la datele altei firme întoarce **404, nu 403**, ca să nu confirmăm că resursa există.

## Endpoint-uri

### Echipa VITIM — `/api/v1/admin` (doar `super_admin` / `vitim_admin`)

| Metodă | Rută | Descriere |
|---|---|---|
| GET | `/organizations?q=&status=` | Lista firmelor, cu abonamentul |
| POST | `/organizations` | `name`, `plan`, opțional `owner_email`, `owner_name`, `company_name`, `vat_id`, `country`, `timezone`, `default_language`. Un proprietar nou primește linkul de setare a parolei |
| GET | `/organizations/{id}` | Detalii |
| PATCH | `/organizations/{id}` | Profil și `status` (active / suspended). `plan` nu se schimbă aici |

### Firmă — `/api/v1/orgs/{slug}`

| Metodă | Rută | Permisiune | Descriere |
|---|---|---|---|
| GET | `/sites` | view_reports | Site-uri, cu `public_key` activ |
| POST | `/sites` | manage_sites | `domain`, `platform`, `name?`, `allowed_origins?`. Răspunsul conține **o singură dată** `secret` |
| GET/PATCH | `/sites/{id}` | view_reports / manage_sites | Domeniul nu se schimbă |
| POST | `/sites/{id}/rotate-keys` | manage_sites | Revocă cheile vechi, întoarce cheia publică și secretul nou |
| GET | `/agents` | view_reports | |
| POST | `/agents` | manage_agents | `name`, `site_id?`, `status?`, `default_language?`, `model_configuration?`, `system_configuration?` |
| GET/PATCH/DELETE | `/agents/{id}` | view_reports / manage_agents | Configurația e validată. Cheile care seamănă a secret sunt respinse |
| GET | `/users` | manage_users | Membrii firmei |
| POST | `/users` | manage_users | Invitație: `email`, `name`, `role`. Un admin nu poate crea proprietari |
| PATCH/DELETE | `/users/{membershipId}` | manage_users | Schimbare de rol / eliminare. Ultimul proprietar e protejat |
| GET | `/contacts?q=&source=` | view_contacts | Căutare după nume, email, telefon, firmă |
| POST | `/contacts` | manage_contacts | `first_name`, `last_name`, `email`, `phone`, `whatsapp`, `company`, `language`, `source`, `custom_fields`, `external_ids[{provider,id}]` — toate opționale |
| GET | `/contacts/{id}` | view_contacts | Include `identities` și `consents[canal][scop]` (starea curentă) |
| PATCH | `/contacts/{id}` | manage_contacts | `source`, `whatsapp` și `external_ids` nu se modifică aici |
| DELETE | `/contacts/{id}` | delete_data | Ștergere GDPR (cascade: identități, consimțăminte, lead-uri). Suprimările rămân |
| GET | `/contacts/{id}/consents` | view_contacts | Istoricul complet |
| POST | `/contacts/{id}/consents` | manage_consent | `channel` (email/sms/whatsapp/phone), `purpose`, `status`, `source`, `occurred_at?`, `metadata?`. IP-ul, user agent-ul și utilizatorul se înregistrează automat |
| GET | `/leads?status=&intent=&contact_id=&assigned_to=` | view_leads | |
| POST | `/leads` | manage_leads | `contact_id` (din aceeași firmă) + `status`, `intent`, `score`, `assigned_to`, `summary`, `site_id`, `agent_id`, `value_amount`, `currency` |
| GET/PATCH | `/leads/{id}` | view_leads / manage_leads | `contact_id` nu se schimbă |
| DELETE | `/leads/{id}` | delete_data | |

### Exemplu

```http
POST /api/v1/orgs/vitim-demo-auto/contacts
Content-Type: application/json
X-XSRF-TOKEN: …

{"first_name": "Andrei", "phone": "0700 000 001", "email": "andrei@example.test"}
```

```json
{"data": {"id": 12, "organization_id": 3, "first_name": "Andrei", "email": "andrei@example.test",
  "phone": "+40700000001", "source": "api", "status": "active",
  "identities": [{"type": "email", "normalized_value": "andrei@example.test", "primary": true}, {"type": "phone", "normalized_value": "+40700000001", "primary": true}]}}
```

## API conector (plugin WordPress / conector PHP) — `/connector/v1`

Fără sesiune. Fiecare cerere are antetele `X-Vitim-Key` (cheia publică `pk_…`), `X-Vitim-Timestamp` (secunde Unix), `X-Vitim-Nonce` (16–128 caractere, unic) și
`X-Vitim-Signature` = `hex(HMAC-SHA256(secret, timestamp + "." + nonce + "." + body))`. Fereastră de 5 minute, nonce-ul nu se poate refolosi, cheie revocată → `401 invalid_signature`. Limită: 30 de cereri/minut per cheie și IP.

| Metodă | Rută | Corp | Efect |
|---|---|---|---|
| POST | `/heartbeat` | `platform` (wordpress/custom), `site_url`, `connector_version`, opțional `php_version`, `core_version`, `core_update`, `theme`, `plugins_total`, `plugin_updates[{name,from,to}]`, `theme_updates`, `app_version`, `disk_free_mb`, `https` | Actualizează starea site-ului, `last_seen_at`, `verification_status` (`verified` / `mismatch` după domeniul raportat) |
| POST | `/scan` | `issues[{code, severity (critical/warning/info), title, details?, fix?}]` (max. 300) | Deschide / actualizează problemele site-ului; cele care lipsesc se închid. `fix` se păstrează doar dacă e în lista permisă (`App\Services\Remediation`) |
| POST | `/backup` | `status` (ok/failed), `verified`, `started_at`, `finished_at`, `db_bytes`, `files_bytes`, `files_count`, `location`, `kept`, `error` | Înregistrează backup-ul, reevaluează problemele de backup, o intrare săptămânală în jurnal |
| GET | `/plugin` | — (public) | Versiunea curentă a pluginului și adresa pachetului, pentru actualizarea din WordPress |
| POST | `/worklog` | `entries[{ref, category, title, description?, performed_at?}]` (max. 50) | Lucrări vizibile clientului, sursa `plugin`; același `ref` pe același site nu se dublează |

### În sens invers: panou → plugin

`POST {command_url}` (raportată de plugin în heartbeat, acceptată doar pe domeniul site-ului, https), aceeași semnătură și aceleași antete.
Corp: `{command_id, action, target?}`. `action` ∈ `scan`, `backup` (pornește în fundal), `update_plugin` (țintă: fișierul pluginului), `update_all_plugins`, `update_theme` (țintă: tema), `update_core`, `reinstall_core`,
`delete_debug_log`, `delete_readme`, `disable_xmlrpc`, `disable_file_edit`, `block_php_uploads`, `allow_indexing`. Răspuns: `{ok, message, issues}` (o scanare nouă).
Pluginul refuză orice altă acțiune și, dacă clientul a oprit remedierile, orice acțiune în afară de `scan`.

## Evenimente de domeniu emise (pentru automatizările viitoare)

`contact.created`, `contact.updated`, `contact.deleted`, `consent.changed`, `lead.created`, `lead.status_changed`, `lead.assigned`, `lead.deleted`, `agent.created`, `message.sent`, `message.cancelled`, `message.failed`.

Planificate: `conversation.started`, `conversation.message_received`, `conversation.closed`, `appointment.*`, `order.*`, `campaign.sent`, `message.delivered`, `message.read`, `message.clicked`.
