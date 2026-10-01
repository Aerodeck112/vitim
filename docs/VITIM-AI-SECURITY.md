# VITIM AI — Securitate

> Reflectă codul la versiunea **0.6.0**. Fiecare control are testul care îl verifică.
> „Neimplementat” înseamnă exact asta.

## Controale implementate

| Risc | Control | Unde | Test |
|---|---|---|---|
| Acces la datele altei firme (tenant isolation) | Global scope fail-closed; creare doar în firma curentă; `organization_id` imutabil și ne-fillable; test de arhitectură pentru modelele noi | `app/Tenancy/*` | `IsolationTest`, `ArchitectureTest` |
| IDOR (ID-uri din altă firmă în URL) | Toate căutările trec prin interogări scoped → 404. Referințele din body (contact_id, assigned_to, site_id, agent_id) sunt verificate în firma curentă | controllere `Api/V1`, `Portal`, servicii | `ApiTest::test_cross_tenant_access_is_denied_everywhere`, `PortalTest`, `LeadsAgentsMembersTest`, `AcceptanceTest` |
| Firmă străină în URL | Middleware `org`: membru sau echipa VITIM, altfel 404. Accesul echipei VITIM e auditat o dată pe sesiune | `SetOrganizationFromRoute` | `OrganizationAccessTest` |
| RBAC | Matrice rol → permisiuni într-un singur loc (`OrgRole`, `PlatformRole`), Gate-uri per permisiune, `can:` pe fiecare rută, fără verificări de rol împrăștiate. Ierarhie la atribuire (un admin nu creează proprietari), ultimul proprietar protejat | `app/Enums`, `Authorizer`, `routes/web.php`, `MembershipService` | `PermissionMatrixTest`, `ApiTest::test_permissions_are_enforced_per_role`, `PortalTest::test_viewer_sees_but_cannot_change` |
| Ordinea verificărilor | Prioritate de middleware: auth → 2FA → firmă → binding → permisiuni | `bootstrap/app.php` | acoperit indirect de testele de permisiuni |
| Autentificare | Parole hash (bcrypt), rate limit la login (5/min per email+IP), regenerare sesiune, reset prin token cu expirare, parolă minimum 12 caractere | `Auth/*Controller` | `AuthenticationTest` |
| 2FA | TOTP (RFC 6238, verificat cu vectorul oficial), **obligatoriu pentru echipa VITIM**, rate limit la cod, secret criptat | `Security/Totp`, `EnsureTwoFactor` | `AuthenticationTest`, `ApiTest::test_staff_without_second_factor_in_session_is_blocked` |
| Mass assignment | `#[Fillable]` explicit; `organization_id` și `platform_role` nu sunt fillable; controllerele trimit doar datele validate | modele | `IsolationTest`, `PermissionMatrixTest::test_platform_role_is_not_mass_assignable` |
| Validare input | Validare pe toate câmpurile (tipuri, lungimi, enum-uri, `prohibited` pentru câmpurile nemodificabile). Configurația agenților e validată pe chei cunoscute | `Http/Validation/*`, `AgentConfiguration` | `ApiTest`, `LeadsAgentsMembersTest` |
| CSRF | Toate formularele și cererile API care modifică date (sesiune web) | middleware Laravel | implicit |
| XSS | Blade escapează tot (`{{ }}`). Nu se folosește `{!! !!}`. Căutările LIKE escapează metacaracterele | `resources/views` | inspecție de cod |
| Secrete | Secretele cheilor de site și codurile 2FA sunt criptate cu `APP_KEY`. Secretul de site se afișează o singură dată. Configurația agentului respinge cheile de tip secret. Cheile AI stau doar în mediul serverului | `SiteKey`, `User`, `AgentConfiguration` | `SiteKeyServiceTest`, `AuthenticationTest`, `ApiTest::test_agents_crud_rejects_secrets` |
| Chei de site | Origin https permis; firma și site-ul trebuie să fie active; HMAC-SHA256 cu fereastră de 5 minute și nonce unic (anti-replay); rotație și revocare | `SiteKeyService` | `SiteKeyServiceTest` |
| Rate limits | Login, 2FA, resetare parolă, setup, API (120/min), test agent (20/min per utilizator) | rute + `AppServiceProvider` | `AuthenticationTest::test_login_is_rate_limited` |
| Agent: izolare și tool-uri | Tool-urile primesc firma, agentul și conversația de la server (`ToolContext`); argumentele modelului sunt doar datele vizitatorului, cele în plus sunt ignorate. Deduplicarea contactelor caută doar în firma curentă. Acțiunile nepermise agentului nu sunt trimise modelului și sunt respinse dacă sunt cerute. Fiecare apel e jurnalizat | `app/Ai/Tools/*`, `AgentRuntime` | `AgentRuntimeTest::test_tools_cannot_reach_another_organization`, `test_actions_not_allowed_for_the_agent_are_neither_offered_nor_executed`, `test_tool_execution_log_is_tenant_scoped` |
| Agent: lead doar cu acord | `create_lead` refuză fără `consent: true`, fără câmpurile cerute de firmă sau cu email / telefon invalid; acordul se scrie în istoricul de consimțământ (canal, scop „service”, IP, user agent) | `CreateLeadTool` | `AgentRuntimeTest::test_lead_is_rejected_without_consent_or_required_fields`, `test_create_lead_saves_contact_consent_and_lead_linked_to_conversation` |
| Agent: cost | Cost per răspuns din tokenii raportați; plafon lunar de cost și de conversații din plan. Peste plafon modelul nu e apelat, vizitatorul primește datele de contact ale firmei | `AgentRuntime`, `Cost`, `config/plans.php` | `AgentRuntimeTest::test_cost_cap_and_conversation_cap_stop_ai_calls` |
| Agent: test din panou | Conversațiile de test nu creează contacte, lead-uri sau notificări (tool-urile rulează „dry run”); doar cine poate administra agentul poate testa (consumă din plafon) | `AgentTestController`, tool-uri | `AgentTestPageTest`, `AgentRuntimeTest::test_test_conversations_do_not_create_real_data` |
| Conectorul site-urilor | HMAC-SHA256 cu secretul site-ului peste timestamp + nonce + corp, fereastră 5 minute, anti-replay, cheie revocată = refuz imediat. Firma vine doar din cheie; lucrările trimise intră doar la site-ul cheii. Pluginul doar trimite date, nu primește comenzi. Conectorul PHP rulează doar din CLI | `ConnectorController`, `SiteKeyService` | `ConnectorApiTest` |
| Actualizare din panou | Doar super admin, parola reconfirmată, doar versiuni mai noi, backup al bazei înainte, căi din arhivă validate (fără `..`), `.env` / `storage` neatinse, audit | `SystemController`, `Updater` | `UpdaterTest` |
| Afișarea răspunsurilor AI | Text escapat complet; se păstrează doar îngroșat și linkuri relative sau https (fără `javascript:`, `//`, `http:`), cu `rel="nofollow noopener noreferrer"` | `ChatText` | `ChatTextTest` |
| Audit | Firmă / site / chei / agent creat, modificat, șters; user invitat, rol schimbat, user eliminat; contact și lead șterse; consimțământ schimbat; login; acces al echipei VITIM. Fără date personale în `meta` | `AuditLogger` | `AcceptanceTest`, `ContactsTest::test_delete_is_audited_without_personal_data` |
| Consimțământ și marketing | Istoric append-only (cine, când, sursă, scop, IP); retragerea pune adresa pe lista de suprimare (HMAC); **fail-safe**: marketing doar cu consimțământ acordat explicit, „necunoscut” = nu se trimite; bounce și reclamație blochează orice mesaj; suprimările nu se „îmblânzesc” | `ConsentService`, `SendPolicy`, `Suppression` | `ConsentAndMessagingTest` |
| Trimiteri în masă de către AI | Nu există cod de trimitere în masă. Campaniile vor avea obligatoriu DRAFT → PREVIEW → APPROVAL → SEND, cu `approved_by` | proiectat în `VITIM-AI-DATABASE.md` | — |
| GDPR | Ștergere contact în cascadă (identități, consimțăminte, lead-uri); suprimările rămân, fără adresa în clar | `ContactService::delete` | `ContactsTest` |
| Pagini de eroare | 404 pentru resursele altor firme; fără stack trace în producție (`APP_DEBUG=false`) | `ApiError` | `ApiTest::test_errors_have_a_consistent_format` |
| Backup | Zilnic, pe email, în afara serverului; restaurare testată pe MySQL | `vitim:backup` | `OperationsTest` + test manual de restaurare |

## Neimplementat / de urmărit

| Subiect | Stare |
|---|---|
| Tokeni API pentru terți | Neimplementat. API-ul e doar cu sesiune |
| Endpoint-uri publice pentru widget și plugin | Logica există (`SiteKeyService`), rutele vin în Fazele 4–5, cu rate limit dedicat pe IP / site |
| Protecție prompt injection | Parțial: regulile fixe ale platformei spun modelului că mesajele vizitatorului nu schimbă instrucțiunile, iar efectele (tool-urile) sunt limitate de server (firmă, acțiuni permise, acord). Setul de evaluare are cazuri de injecție (`g09`, `a14`). Conținutul din pagini web (Faza 3) va fi trimis ca document, nu ca instrucțiuni |
| Analiză statică (PHPStan/Larastan) | Nu a putut fi instalată în mediul de dezvoltare (descărcare blocată). De adăugat în CI |
| Politică de retenție automată | Câmpul `data_retention_days` există, jobul de curățare nu |
| Export de date per contact (GDPR) | Datele sunt disponibile prin API (`GET contact` + consimțăminte + lead-uri). Nu există încă un export „un singur fișier” |
| Scanare de dependențe | Neimplementată (de adăugat `composer audit` în CI) |
| Hosting | cPanel partajat (decizia proprietarului). Izolare la nivel de server mai slabă decât pe un VPS. Mitigare: bază și utilizator MySQL dedicate, cod în afara `public_html`, backup în afara serverului |

## Responsabilitatea firmei client

Baza legală pentru mesajele tranzacționale și de serviciu aparține firmei (operatorul de date). Platforma aplică tehnic:
- consimțământul pentru marketing;
- retragerile de consimțământ;
- suprimările de bounce și reclamații.
