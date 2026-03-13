-- /local/modules/slavko.schedule/install/db/mysql/install.sql
CREATE TABLE IF NOT EXISTS sk_schedule (
    `ID` INT(11) NOT NULL AUTO_INCREMENT,
    `WORKER_ID` INT(11) NOT NULL DEFAULT '0',
    `SCHEDULE` LONGTEXT,
    PRIMARY KEY (ID),
    UNIQUE KEY IDX_WORKER_ID (WORKER_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;