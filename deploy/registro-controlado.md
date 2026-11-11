# Despliegue controlado del alta con contrato

> **Estado:** preparación. El alta real **no está activada**. La guarda
> `REGISTRATION_LEGACY_GUARD_ENABLED` sigue en `true` hasta confirmación expresa.

Este documento cubre el despliegue y la prueba controlada del flujo:

`/registro` → PDF individual → aceptación auditada → correo → verificación → activación.

---

## 1. Variables de `.env` de producción

Los valores legales que ya están definidos en el `.env` actual son:

```dotenv
REGISTRATION_CONTRACT_SUPPORT_EMAIL=palimaynard@gmail.com
REGISTRATION_CONTRACT_IDIRECTO_CONTACT="IDIRECTO MAYORISTA S.L., Poeta Rosalia ..., Zaragoza"
REGISTRATION_CONTRACT_LIABILITY_LIMIT="600,00 EUR"
REGISTRATION_CONTRACT_PREAVISO_DAYS=30
REGISTRATION_CONTRACT_EXPORT_DAYS=30
REGISTRATION_CONTRACT_NEGOTIATION_DAYS=30
REGISTRATION_CONTRACT_CONFIDENTIALITY_YEARS=3
```

> **Revisar antes de la prueba:** el valor actual de
> `REGISTRATION_CONTRACT_IDIRECTO_CONTACT` contiene literalmente `...` en el
> `.env`. Si el texto aprobado es otro, corregirlo antes de generar contratos.
> `REGISTRATION_MAIL_TRANSPORT` y `REGISTRATION_LEGACY_GUARD_ENABLED` no están
> escritos actualmente en el `.env`; el preflight los avisa. Añadirlos de forma
> explícita en producción.

El bloque de producción debe quedar así. Los valores marcados como
`<...>` no están en el `.env` de esta máquina y **no se inventan**: hay que
confirmarlos con el responsable de producción.

```dotenv
# Entorno
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio-produccion>
TRUSTED_PROXIES=<IP-del-proxy>,<CIDR-del-proxy>
# Si no hay proxy delante, dejar vacío: TRUSTED_PROXIES=

# Alta y guarda
REGISTRATION_COMPLETION_ENABLED=true
# true durante el despliegue y la prueba. Solo pasa a false con confirmación
# explícita del responsable.
REGISTRATION_LEGACY_GUARD_ENABLED=true
REGISTRATION_CODE_DIGITS=8
REGISTRATION_CODE_TTL=900
REGISTRATION_CODE_MAX_ATTEMPTS=5
REGISTRATION_RESEND_COOLDOWN=60
REGISTRATION_MAX_RESENDS=5
REGISTRATION_RESEND_WINDOW=3600
REGISTRATION_PENDING_TTL=604800

# Valores legales aprobados (los del bloque anterior)
REGISTRATION_CONTRACT_SUPPORT_EMAIL=palimaynard@gmail.com
REGISTRATION_CONTRACT_IDIRECTO_CONTACT="IDIRECTO MAYORISTA S.L., Poeta Rosalia ..., Zaragoza"
REGISTRATION_CONTRACT_LIABILITY_LIMIT="600,00 EUR"
REGISTRATION_CONTRACT_PREAVISO_DAYS=30
REGISTRATION_CONTRACT_EXPORT_DAYS=30
REGISTRATION_CONTRACT_NEGOTIATION_DAYS=30
REGISTRATION_CONTRACT_CONFIDENTIALITY_YEARS=3

# Motor PDF. Vacío = detección automática de php-tcpdf.
CONTRACT_TCPDF_PATH=

# Correo real: api (file solo se permite fuera de producción)
REGISTRATION_MAIL_TRANSPORT=api
EMAIL_API_ENABLED=true
API_EMAIL=https://<api-produccion>/email/v1/
EMAIL_USER=<mantener el usuario actual>
EMAIL_PASS=<mantener la contraseña actual>
EMAIL_FROM=no-responder@idirecto.es
EMAIL_FROM_NAME="IDIRECTO MAYORISTA"
# Opcional; si se usa, debe ser un buzón válido:
EMAIL_REPLY_TO=
```

Reglas:

- `EMAIL_USER` y `EMAIL_PASS` **no se imprimen ni se versionan**.
- En producción `API_EMAIL` debe ser **HTTPS**.
- `REGISTRATION_MAIL_TRANSPORT=file` es solo para pruebas locales.
- `TRUSTED_PROXIES` debe contener **solo** las IP/CIDR de los proxies reales.
  No usar `0.0.0.0/0` salvo que toda la cadena esté controlada.
- El `.env` debe quedar con permisos restrictivos (`0640` o ACL equivalente) y
  nunca dentro de `public/`.

---

## 2. Checklist de despliegue

### Antes de tocar producción

- [ ] Copia de seguridad de la base de datos y de `storage/contracts/`.
- [ ] Ventana de mantenimiento o aviso al responsable.
- [ ] Confirmar que el flujo sigue cerrado:
      `REGISTRATION_LEGACY_GUARD_ENABLED=true`.
- [ ] Confirmar que no hay altas pendientes ajenas a la prueba.

### Código y migración

- [ ] Desplegar el código.
- [ ] Ejecutar `php database/migrate.php`.
- [ ] Comprobar que la migración `015_registration_acceptance_evidence.sql`
      aparece en `mt_migrations` y que existen:
      `source_template_sha256`, `ip_address` y `user_agent`.
- [ ] No ejecutar la migración dentro de una transacción: MySQL hace `COMMIT`
      implícito en DDL.

### php-tcpdf

- [ ] Instalar el paquete del sistema `php-tcpdf` o configurar
      `CONTRACT_TCPDF_PATH`.
- [ ] Comprobar que el usuario web puede leer el fichero de TCPDF y sus fuentes.
- [ ] No añadir TCPDF a Composer: el proyecto no incorpora dependencias PHP.

### Permisos de `storage/contracts/*`

Recomendado (o ACL equivalente para el usuario web):

```text
storage/contracts                 0750  www-data:www-data
storage/contracts/templates       0750  www-data:www-data
storage/contracts/generated       0750  www-data:www-data
storage/contracts/outbox          0750  www-data:www-data
storage/contracts/templates/*.pdf 0640  www-data:www-data
storage/contracts/templates/*.html 0640 www-data:www-data
storage/contracts/generated/*.pdf 0640  www-data:www-data
```

- [ ] `templates/` legible por el usuario web.
- [ ] `generated/` y `outbox/` escribibles por el usuario web.
- [ ] Nada de `0777` ni escritura por `other`.
- [ ] `storage/contracts` fuera de `public/`.
- [ ] Los `.htaccess` de `storage/contracts`, `generated` y `outbox` existen.
- [ ] Si se usan ACL, ejecutar el preflight **como usuario web**.

### Bloqueo web en Apache

- [ ] `AllowOverride All` en el directorio del proyecto para que se aplique
      `.htaccess`.
- [ ] Comprobar:
      `/.env`, `/.env.example`, `/storage/contracts/`,
      `/storage/contracts/templates/...html`, `/storage/contracts/generated/`
      y `/storage/logs/` devuelven `403` o `404`.
- [ ] Comprobar que no se sirve ningún PDF generado por URL.

### Bloqueo web en nginx

- [ ] Usar el `server` block de `deploy/nginx-site.conf.tpl`.
- [ ] Confirmar que existe el `location` que bloquea
      `.agents|.git|app|config|database|deploy|storage|tools|vendor`.
- [ ] `nginx -t` y recarga.
- [ ] Repetir las mismas comprobaciones HTTP que en Apache.

### Proxies de confianza

- [ ] Identificar la IP/CIDR real del reverse proxy/CDN.
- [ ] Configurar `TRUSTED_PROXIES`.
- [ ] Comprobar que `REMOTE_ADDR` es el proxy y que `X-Forwarded-For` solo se
      acepta desde esa lista.
- [ ] No confiar en `X-Forwarded-For` si la aplicación recibe tráfico directo
      de Internet sin proxy.

### Correo

- [ ] `EMAIL_API_ENABLED=true`.
- [ ] `API_EMAIL` HTTPS en producción.
- [ ] `EMAIL_USER`/`EMAIL_PASS` presentes, sin imprimirlos.
- [ ] `EMAIL_FROM` y `EMAIL_FROM_NAME` verificados.
- [ ] `REGISTRATION_MAIL_TRANSPORT=api`.
- [ ] No enviar ningún correo durante el preflight.

### Preflight obligatorio

```bash
php tools/registration-preflight.php \
  --phase=predeploy \
  --base-url=https://<dominio-produccion> \
  --web-user=www-data
```

Debe terminar con **0 fallos**. Los avisos se revisan antes de continuar.

---

## 3. Script de comprobación previa

`tools/registration-preflight.php`:

- solo lee configuración, base de datos, permisos y respuestas HTTP;
- usa únicamente `SELECT`;
- no hace `INSERT`, `UPDATE` ni `DELETE`;
- no llama a `/send-email-adjunto` ni envía correos;
- genera un PDF temporal mínimo y lo borra para probar TCPDF;
- no imprime `EMAIL_USER` ni `EMAIL_PASS`;
- por defecto exige `REGISTRATION_LEGACY_GUARD_ENABLED=true`;
- para `--phase=go-live` exige además `--allow-open-guard` y la guarda abierta.

Opciones:

```text
--phase=predeploy|go-live
--base-url=https://...
--web-user=www-data
--skip-http
--no-pdf-smoke
--allow-open-guard
--strict
```

---

## 4. Prueba real controlada

### Requisitos

- Una cuenta de prueba **real** en idirecto. El correo del formulario debe
  coincidir exactamente con el correo de esa cuenta; no se puede redirigir a
  otro correo sin cambiar el flujo.
- Un slug de prueba con prefijo `prueba-` (por ejemplo,
  `prueba-alta-2026-11-11`).
- El correo del responsable de la prueba como correo de la cuenta.
- La guarda cerrada hasta que el responsable confirme la prueba.

### Antes de abrir

- [ ] Ejecutar el preflight de predeploy y revisar avisos.
- [ ] Anotar los contadores actuales:
      `mt_stores`, `mt_store_registrations`, `mt_contract_acceptances` y
      `mt_registration_verifications`.
- [ ] Confirmar que el PDF y la plantilla responden 403/404 por URL.
- [ ] Confirmar que la IP que se guardará es la del cliente y no la del proxy.

### Abrir solo durante la prueba

Con confirmación explícita del responsable:

```dotenv
REGISTRATION_LEGACY_GUARD_ENABLED=false
```

Recargar PHP-FPM/Apache y ejecutar el preflight de go-live:

```bash
php tools/registration-preflight.php \
  --phase=go-live \
  --allow-open-guard \
  --base-url=https://<dominio-produccion> \
  --web-user=www-data
```

### Ejecutar el alta

1. Abrir `/registro`.
2. Usar la cuenta de prueba y el correo acordado.
3. Enviar el formulario.
4. Comprobar en base de datos:
   - tienda `status=0`;
   - usuario `active=0`;
   - alta `pending_verification`;
   - aceptación con versión, IP, user-agent y hashes;
   - código solo como hash.
5. Recibir el correo con el PDF adjunto.
6. Abrir la pantalla de verificación e introducir el código.
7. Comprobar:
   - tienda `status=1`;
   - usuario `active=1`;
   - alta `verified`;
   - código `consumed_at` informado;
   - una segunda verificación no duplica aceptación ni código.

### Rollback si falla

**Primero**, cerrar de nuevo el alta:

```dotenv
REGISTRATION_LEGACY_GUARD_ENABLED=true
```

Recargar PHP-FPM/Apache. No dejar la guarda abierta mientras se investiga.

Después, conservar evidencia y desactivar la tienda de prueba. La aceptación es
evidencia inmutable: **no borrarla por defecto**. Ejecutar en una transacción,
sustituyendo los IDs:

```sql
START TRANSACTION;

SELECT id, status
  FROM mt_store_registrations
 WHERE id = :registration_id
 FOR UPDATE;

UPDATE mt_registration_verifications
   SET superseded_at = UTC_TIMESTAMP()
 WHERE registration_id = :registration_id
   AND consumed_at IS NULL
   AND superseded_at IS NULL;

UPDATE mt_store_registrations
   SET status = 'cancelled'
 WHERE id = :registration_id;

UPDATE mt_stores
   SET status = 0
 WHERE id = :store_id;

UPDATE mt_store_users
   SET active = 0
 WHERE id = :store_user_id
   AND store_id = :store_id;

COMMIT;
```

Si el responsable legal autoriza eliminar por completo la prueba, hacerlo en
este orden y solo después de exportar la evidencia:

```sql
START TRANSACTION;

DELETE FROM mt_registration_verifications WHERE registration_id = :registration_id;
DELETE FROM mt_contract_acceptances    WHERE registration_id = :registration_id;
DELETE FROM mt_store_registrations     WHERE id = :registration_id;
DELETE FROM mt_store_users             WHERE id = :store_user_id;
DELETE FROM mt_stores                  WHERE id = :store_id;

COMMIT;
```

El PDF privado se borra del sistema de ficheros solo cuando ya no se conserve
la aceptación. Si se conserva evidencia, se conserva también el PDF para poder
verificar el hash.

Después del rollback:

- ejecutar el preflight de predeploy;
- confirmar que no quedan altas pendientes de la prueba;
- dejar la guarda en `true`.

### Cierre de la prueba

- Si la prueba es correcta, decidir con el responsable si la tienda de prueba
  se suspende/elimina o se conserva como escaparate.
- No dejar una tienda de prueba activa y publicada sin decisión expresa.
- Solo después de la validación y la confirmación explícita se puede pasar a
  `REGISTRATION_LEGACY_GUARD_ENABLED=false` de forma permanente.

---

## 5. Retención de PDFs (propuesta, no implementada)

Propuesta para revisar con legal/DPO:

- **Contratos aceptados y clientes activos:** conservar mientras dure la
  relación contractual y, después, el plazo legal aplicable (a confirmar;
  referencia habitual: 6 años para documentación mercantil).
- **Altas caducadas, canceladas o nunca verificadas:** conservar 30 días y
  eliminar, salvo que exista una incidencia o reclamación abierta.
- **PDFs individuales:** no se sirven por URL; se guardan con hash SHA-256 en
  `mt_contract_acceptances.pdf_sha256`.
- **Purga:** tarea programada con `--dry-run`, informe de ficheros/hashes,
  comprobación de que no se borra un PDF con aceptación vigente, y registro de
  auditoría sin datos personales.
- **Copia de seguridad:** los PDFs que se conserven deben entrar en la política
  de backup y restauración.
- **Organización:** para nuevas altas, valorar
  `generated/<AAAA>/<MM>/<uuid>.pdf`, manteniendo la ruta relativa en BD.
- **Outbox de pruebas:** el buzón falso de `storage/contracts/outbox` no debe
  existir en producción; si se usa en local, se purga al terminar cada prueba.

---

## 6. Outbox transaccional (propuesta, no implementada)

Problema actual: el correo se envía dentro de la transacción para poder hacer
rollback si falla. Eso mantiene bloqueos mientras responde la API y no elimina
el caso de “correo enviado y commit fallido”.

Propuesta:

1. Añadir una tabla `mt_email_outbox` (prefijo `mt_`, migración idempotente):
   ```text
   id, registration_id, type, idempotency_key, recipient_email_cifrado,
   subject, body_html_cifrado, attachment_path, attachment_sha256,
   status (pending|sending|sent|failed|dead), attempts, next_attempt_at,
   last_error_code, sent_at, created_at, updated_at
   ```
2. En la misma transacción del alta, insertar el alta, la aceptación, el código
   y la fila de outbox. Hacer `COMMIT` sin llamar a la API de correo.
3. Un worker CLI (`php tools/outbox-worker.php`) toma filas `pending` con
   `SELECT ... FOR UPDATE SKIP LOCKED`, llama a `/send-email-adjunto`, marca
   `sent` o `failed` y aplica backoff exponencial.
4. Idempotencia por `registration_id + type + versión del código`.
5. El PDF se conserva hasta que el correo esté enviado y se cumpla la retención;
   nunca se borra antes de que la aceptación deje de conservarse.
6. Alertas si hay filas `dead` o `pending` antiguas; métricas sin PII.
7. El contenido y el destinatario son datos personales: cifrado en reposo,
   acceso restringido y retención corta tras el envío.
8. No guardar códigos en claro en logs ni en la outbox; el código solo debe
   viajar en el cuerpo cifrado del mensaje y en memoria.

No se implementa todavía. Requiere migración, worker, cifrado de columnas y
pruebas de entrega/rollback.

---

## Confirmación

Mientras la guarda siga en `true` y no se ejecute la prueba autorizada:

- no se crea ninguna tienda real;
- no se emite ninguna aceptación;
- no se envía ningún correo;
- `/registro` permanece bloqueado para el alta real.

La activación requiere confirmación explícita del responsable.
