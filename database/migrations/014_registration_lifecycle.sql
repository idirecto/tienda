-- =============================================================================
-- 014 - Alta pendiente, contrato aceptado y verificacion de correo
-- -----------------------------------------------------------------------------
-- Solo crea estructuras nuevas y un indice unico en `mt_stores`. No modifica
-- filas existentes ni las tablas del mayorista.
--
-- IMPORTANTE:
--   - No se ejecuta desde esta tarea: queda pendiente de autorizacion.
--   - MySQL hace COMMIT implicito en DDL; no se envuelve en transaccion.
--   - Es idempotente. El rollback esta en database/rollbacks/.
--   - No inserta versiones, contratos, altas ni datos de ejemplo.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- Una cuenta de IDIRECTO solo puede originar una tienda.
-- `NULL` sigue permitido para tiendas antiguas/demo sin cuenta enlazada.
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD UNIQUE KEY `uq_mt_stores_id_tienda_idirecto` (`id_tienda_idirecto`)',
  'DO 0') FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_stores'
    AND INDEX_NAME = 'uq_mt_stores_id_tienda_idirecto');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Versiones aprobadas del contrato. El PDF original vive fuera de la web.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_contract_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_key` varchar(40) NOT NULL DEFAULT 'access_web',
  `version_label` varchar(80) NOT NULL,
  `effective_date` date NOT NULL,
  `template_path` varchar(255) NOT NULL COMMENT 'Ruta relativa a storage/contracts/templates',
  `template_sha256` char(64) NOT NULL,
  `page_count` smallint unsigned NOT NULL DEFAULT 0,
  `approved_by` varchar(150) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_contract_versions_label` (`document_key`, `version_label`),
  UNIQUE KEY `uq_mt_contract_versions_sha` (`template_sha256`),
  KEY `idx_mt_contract_versions_active` (`document_key`, `active`, `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Versiones aprobadas de contratos';

-- -----------------------------------------------------------------------------
-- Alta operativa. La tienda y el usuario pueden existir ya, pero permanecen
-- inactivos (mt_stores.status=0, mt_store_users.active=0) hasta verificar.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_store_registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_uuid` char(36) NOT NULL,
  `store_id` int unsigned NOT NULL,
  `store_user_id` int unsigned NOT NULL,
  `id_tienda_idirecto` smallint unsigned NOT NULL,
  `email` varchar(180) NOT NULL,
  `representative_first_name` varchar(80) NOT NULL,
  `representative_last_name` varchar(120) NOT NULL,
  `representative_role` varchar(120) NOT NULL,
  `representative_phone` varchar(40) NOT NULL,
  `contract_version_id` int unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending_verification'
    COMMENT 'pending_verification|verified|expired|cancelled',
  `email_sent_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_store_registrations_uuid` (`public_uuid`),
  UNIQUE KEY `uq_mt_store_registrations_store` (`store_id`),
  UNIQUE KEY `uq_mt_store_registrations_user` (`store_user_id`),
  UNIQUE KEY `uq_mt_store_registrations_account` (`id_tienda_idirecto`),
  KEY `idx_mt_store_registrations_status` (`status`, `expires_at`),
  KEY `idx_mt_store_registrations_email` (`email`),
  CONSTRAINT `fk_mt_store_registrations_store`
    FOREIGN KEY (`store_id`) REFERENCES `mt_stores` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_store_registrations_user`
    FOREIGN KEY (`store_user_id`) REFERENCES `mt_store_users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_store_registrations_contract`
    FOREIGN KEY (`contract_version_id`) REFERENCES `mt_contract_versions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Altas de tienda pendientes de verificacion';

-- -----------------------------------------------------------------------------
-- Aceptacion electronica inmutable. No guarda IP por defecto.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_contract_acceptances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_uuid` char(36) NOT NULL,
  `registration_id` bigint unsigned NOT NULL,
  `store_id` int unsigned NOT NULL,
  `client_account_id` smallint unsigned NOT NULL,
  `contract_version_id` int unsigned NOT NULL,
  `template_sha256` char(64) NOT NULL,
  `pdf_path` varchar(255) NOT NULL COMMENT 'Ruta privada; nunca URL publica',
  `pdf_sha256` char(64) NOT NULL,
  `representative_first_name` varchar(80) NOT NULL,
  `representative_last_name` varchar(120) NOT NULL,
  `representative_role` varchar(120) NOT NULL,
  `representative_email` varchar(180) NOT NULL,
  `company_legal_name` varchar(150) NOT NULL,
  `company_tax_id` varchar(45) NOT NULL,
  `company_address` varchar(500) NOT NULL,
  `acceptance_text_sha256` char(64) NOT NULL,
  `accepted_at_utc` datetime(6) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Europe/Madrid',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_contract_acceptances_event` (`event_uuid`),
  KEY `idx_mt_contract_acceptances_registration` (`registration_id`, `id`),
  KEY `idx_mt_contract_acceptances_store` (`store_id`, `id`),
  KEY `idx_mt_contract_acceptances_client` (`client_account_id`),
  CONSTRAINT `fk_mt_contract_acceptances_registration`
    FOREIGN KEY (`registration_id`) REFERENCES `mt_store_registrations` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_contract_acceptances_store`
    FOREIGN KEY (`store_id`) REFERENCES `mt_stores` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_contract_acceptances_version`
    FOREIGN KEY (`contract_version_id`) REFERENCES `mt_contract_versions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Evidencia de aceptacion electronica del contrato';

-- -----------------------------------------------------------------------------
-- Codigos de verificacion. Solo se guarda el hash, nunca el codigo en claro.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_registration_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` bigint unsigned NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint unsigned NOT NULL DEFAULT 5,
  `sent_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `superseded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mt_registration_verifications_active`
    (`registration_id`, `superseded_at`, `consumed_at`, `expires_at`),
  KEY `idx_mt_registration_verifications_recent`
    (`registration_id`, `sent_at`),
  CONSTRAINT `fk_mt_registration_verifications_registration`
    FOREIGN KEY (`registration_id`) REFERENCES `mt_store_registrations` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Codigos de verificacion de altas (solo hash)';
