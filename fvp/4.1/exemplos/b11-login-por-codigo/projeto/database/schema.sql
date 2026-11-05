SET foreign_key_checks = 0;

DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    encrypted_password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'participant') NOT NULL DEFAULT 'participant',
    last_login_at TIMESTAMP NULL,
    -- Login por código (FVP B11). O código nunca fica em texto puro: só o hash
    -- (mesmo idioma de encrypted_password), a validade e o contador de tentativas.
    -- "Não há pedido" = login_code_digest NULL. attempts é NOT NULL: como entra em
    -- $columns, o construtor do model precisa dar o padrão 0 (mesma lição do role).
    -- Próximo passo: app/Models/User.php ($columns, construtor e os métodos do código).
    login_code_digest VARCHAR(255) NULL,
    login_code_expires_at DATETIME NULL,
    login_code_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

SET foreign_key_checks = 1;
