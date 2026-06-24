-- Borra y recrea la base entera para poder reimportar el script sin errores.
DROP DATABASE IF EXISTS gestion_materiales_nicolas;

DROP DATABASE IF EXISTS gestion_materiales_nicolas;

CREATE DATABASE gestion_materiales_nicolas CHARACTER
SET
    utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestion_materiales_nicolas;

CREATE TABLE
    localizacion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo ENUM ('PERSONA', 'LUGAR') NOT NULL,
        nombre VARCHAR(150) NOT NULL
    ) ENGINE = InnoDB;

CREATE TABLE
    persona (
        localizacion_id INT PRIMARY KEY,
        email VARCHAR(150),
        rol VARCHAR(50),
        FOREIGN KEY (localizacion_id) REFERENCES localizacion (id)
    ) ENGINE = InnoDB;

CREATE TABLE
    lugar (
        localizacion_id INT PRIMARY KEY,
        direccion VARCHAR(200),
        FOREIGN KEY (localizacion_id) REFERENCES localizacion (id)
    ) ENGINE = InnoDB;

CREATE TABLE
    material (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50) NOT NULL UNIQUE,
        descripcion VARCHAR(255) NOT NULL,
        categoria VARCHAR(100),
        estado ENUM ('DISPONIBLE', 'ASIGNADO', 'EN_REPARACION') NOT NULL DEFAULT 'DISPONIBLE',
        localizacion_actual_id INT NULL,
        activo TINYINT (1) NOT NULL DEFAULT 1,
        fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (localizacion_actual_id) REFERENCES localizacion (id)
    ) ENGINE = InnoDB;

CREATE TABLE
    movimiento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        origen_id INT NULL,
        destino_id INT NOT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (material_id) REFERENCES material (id),
        FOREIGN KEY (origen_id) REFERENCES localizacion (id),
        FOREIGN KEY (destino_id) REFERENCES localizacion (id)
    ) ENGINE = InnoDB;

CREATE TABLE
    validacion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        movimiento_id INT NOT NULL,
        validador_id INT NOT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        observaciones TEXT,
        FOREIGN KEY (movimiento_id) REFERENCES movimiento (id),
        FOREIGN KEY (validador_id) REFERENCES persona (localizacion_id)
    ) ENGINE = InnoDB;

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('PERSONA', 'Ana López');

INSERT INTO
    persona (localizacion_id, email, rol)
VALUES
    (
        LAST_INSERT_ID (),
        'ana.lopez@silicon.com',
        'Técnico'
    );

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('PERSONA', 'Carlos Ruiz');

INSERT INTO
    persona (localizacion_id, email, rol)
VALUES
    (
        LAST_INSERT_ID (),
        'carlos.ruiz@silicon.com',
        'Responsable'
    );

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('LUGAR', 'Almacén Central');

INSERT INTO
    lugar (localizacion_id, direccion)
VALUES
    (LAST_INSERT_ID (), 'Polígono Industrial, Nave 3');

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('LUGAR', 'Oficina Pontevedra');

INSERT INTO
    lugar (localizacion_id, direccion)
VALUES
    (LAST_INSERT_ID (), 'Praza da Peregrina, 5');

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('LUGAR', 'Servicio Técnico');

INSERT INTO
    lugar (localizacion_id, direccion)
VALUES
    (LAST_INSERT_ID (), 'Centro de Reparaciones');

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('LUGAR', 'Oficina Vigo');

INSERT INTO
    lugar (localizacion_id, direccion)
VALUES
    (LAST_INSERT_ID (), 'Rúa do Príncipe, 22');

INSERT INTO
    localizacion (tipo, nombre)
VALUES
    ('LUGAR', 'Almacén Secundario');

INSERT INTO
    lugar (localizacion_id, direccion)
VALUES
    (LAST_INSERT_ID (), 'Polígono A Granxa, Nave 7');

INSERT INTO
    material (
        codigo,
        descripcion,
        categoria,
        localizacion_actual_id
    )
VALUES
    (
        'MAT-0001',
        'Portátil Dell',
        'Informática',
        (
            SELECT
                id
            FROM
                localizacion
            WHERE
                nombre = 'Almacén Central'
        )
    ),
    (
        'MAT-0002',
        'Monitor LG 27"',
        'Informática',
        (
            SELECT
                id
            FROM
                localizacion
            WHERE
                nombre = 'Almacén Central'
        )
    ),
    (
        'MAT-0003',
        'Móvil Samsung',
        'Telefonía',
        (
            SELECT
                id
            FROM
                localizacion
            WHERE
                nombre = 'Oficina Pontevedra'
        )
    ),
    (
        'MAT-0004',
        'Switch Cisco',
        'Redes',
        (
            SELECT
                id
            FROM
                localizacion
            WHERE
                nombre = 'Almacén Secundario'
        )
    ),
    (
        'MAT-0005',
        'Proyector Epson',
        'Audiovisuales',
        (
            SELECT
                id
            FROM
                localizacion
            WHERE
                nombre = 'Oficina Vigo'
        )
    ),
    (
        'MAT-0006',
        'Disco SSD 1TB',
        'Informática',
        (
            SELECT
                id
            FROM
                localizacion
            WHERE
                nombre = 'Almacén Central'
        )
    );