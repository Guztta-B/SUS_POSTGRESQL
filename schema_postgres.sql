-- schema_postgres.sql (inferido a partir do código; ajuste aos seus tipos reais)

CREATE TABLE usuarios_admin (
    id            SERIAL PRIMARY KEY,
    nome          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    senha         VARCHAR(255) NOT NULL,
    perfil        VARCHAR(20)  NOT NULL DEFAULT 'funcionario'
                  CHECK (perfil IN ('admin','gerente','funcionario')),
    ativo         BOOLEAN      NOT NULL DEFAULT TRUE,
    ultimo_acesso TIMESTAMP
);

CREATE TABLE auditoria_log (
    id          SERIAL PRIMARY KEY,
    usuario_id  INTEGER REFERENCES usuarios_admin(id),
    acao        VARCHAR(50)  NOT NULL,
    tabela      VARCHAR(50)  NOT NULL,
    id_registro INTEGER,
    detalhes    TEXT,
    ip          VARCHAR(45),
    user_agent  TEXT,
    criado_em   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE pacientes (
    id         SERIAL PRIMARY KEY,
    nome       VARCHAR(150) NOT NULL,
    cpf        VARCHAR(14)  NOT NULL UNIQUE,
    telefone   VARCHAR(20),
    senha_hash VARCHAR(255) NOT NULL
);

CREATE TABLE funcionarios (
    id   SERIAL PRIMARY KEY,
    nome VARCHAR(150) NOT NULL
);

CREATE TABLE agendamentos (
    id                   SERIAL PRIMARY KEY,
    paciente_id          INTEGER REFERENCES pacientes(id),
    funcionario_id       INTEGER REFERENCES funcionarios(id),
    data_hora            TIMESTAMP NOT NULL,
    tipo                 VARCHAR(50),
    status               VARCHAR(20) NOT NULL DEFAULT 'pendente',
    nome_completo        VARCHAR(150),
    telefone             VARCHAR(20),
    rg                   VARCHAR(20),
    unidade              VARCHAR(100),
    motivo_reagendamento TEXT
);
-- Evita dois atendimentos do mesmo médico no mesmo horário (condição de corrida)
CREATE UNIQUE INDEX uq_agendamento_medico_horario
    ON agendamentos (funcionario_id, data_hora) WHERE funcionario_id IS NOT NULL;

CREATE TABLE estoque_medicamentos (
    id             SERIAL PRIMARY KEY,
    nome           VARCHAR(150) NOT NULL,
    lote           VARCHAR(50)  NOT NULL,
    validade       DATE NOT NULL,
    quantidade     INTEGER NOT NULL DEFAULT 0,
    estoque_minimo INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE estoque_vacinas (
    id              SERIAL PRIMARY KEY,
    nome_vacina     VARCHAR(150) NOT NULL,
    lote            VARCHAR(50)  NOT NULL,
    data_vencimento DATE NOT NULL,
    quantidade      INTEGER NOT NULL DEFAULT 0,
    estoque_minimo  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE fila_triagem (
    id             SERIAL PRIMARY KEY,
    agendamento_id INTEGER NOT NULL REFERENCES agendamentos(id) ON DELETE CASCADE,
    senha          VARCHAR(10) NOT NULL,
    prioridade     VARCHAR(10) NOT NULL DEFAULT 'normal' CHECK (prioridade IN ('normal','urgente')),
    hora_chegada   TIMESTAMP NOT NULL DEFAULT NOW(),
    status_fila    VARCHAR(20) NOT NULL DEFAULT 'aguardando'
                   CHECK (status_fila IN ('aguardando','em_atendimento','concluido'))
);
