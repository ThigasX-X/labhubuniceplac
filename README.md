# LabHub UNICEPLAC

**Central de Reservas e Gestão de Laboratórios Acadêmicos**

Sistema web em PHP (arquitetura MVC) para gerenciar reservas, quadro de horários, ensalamento, controle de chaves, chamados técnicos e relatórios analíticos (BI) dos laboratórios do UNICEPLAC. O acesso é segmentado por perfil: **Professor**, **Coordenação** e **Suporte Técnico**.

---

## Sumário

- [Objetivo](#objetivo)
- [Stack tecnológico](#stack-tecnológico)
- [Arquitetura](#arquitetura)
- [Estrutura de diretórios](#estrutura-de-diretórios)
- [Funcionalidades por perfil](#funcionalidades-por-perfil)
- [Modelo de dados](#modelo-de-dados)
- [Rotas](#rotas)
- [Segurança](#segurança)
- [Pré-requisitos](#pré-requisitos)
- [Instalação e execução](#instalação-e-execução)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Scripts de banco de dados](#scripts-de-banco-de-dados)
- [Usuários de demonstração](#usuários-de-demonstração)
- [Melhorias futuras](#melhorias-futuras)

> 📄 **Documentação técnica completa:** veja o arquivo [`docs/LabHub-Documentacao.pdf`](docs/LabHub-Documentacao.pdf), que detalha cada camada, tabela, fluxo e regra de negócio do sistema.

---

## Objetivo

Modernizar e centralizar os processos de utilização dos laboratórios da instituição, reduzindo conflitos de agendamento, organizando o quadro de horários semanal, controlando a entrega/devolução de chaves e melhorando a comunicação entre professores, coordenação e equipe de suporte.

---

## Stack tecnológico

| Camada | Tecnologia |
|---|---|
| **Linguagem** | PHP 8.2+ (testado em 8.5) |
| **Banco de dados** | MySQL 8.x (PDO, charset `utf8mb4`) |
| **Padrão** | MVC + Front Controller, com camadas DAO/Service |
| **Front-end** | HTML5, CSS3, JavaScript (vanilla) |
| **UI** | Bootstrap 5.3, Bootstrap Icons 1.11 |
| **Gráficos** | Chart.js 4.4 |
| **Calendário** | FullCalendar 6.1 |
| **Autenticação** | Sessão PHP + Google OAuth 2.0 |
| **Gerenciador de pacotes** | Composer |

### Dependências (Composer)

| Pacote | Uso |
|---|---|
| `google/apiclient` | Login social via Google OAuth 2.0 |
| `vlucas/phpdotenv` | Carregamento de variáveis de ambiente (`.env`) |
| `phpmailer/phpmailer` | Biblioteca de e-mail (disponível para envio de verificação) |
| `monolog/monolog` | Biblioteca de logs (disponível) |

---

## Arquitetura

O projeto segue o padrão **MVC** com um único ponto de entrada (Front Controller) e separação em camadas.

```
Requisição HTTP
      │
      ▼
public/index.php  ──►  backend/routes/web.php   (tabela de rotas: page => [Controller, método])
      │
      ▼
 Controller  ──►  Service / Model  ──►  DAO (interface) ──► DAOImpl (PDO)  ──►  MySQL
      │
      ▼
  View (frontend/views/*.php)  ──►  HTML + Bootstrap + Chart.js/FullCalendar
```

- **Front Controller** — `public/index.php` é o único arquivo público executável. Inicia a sessão (cookie `HttpOnly`/`SameSite=Lax`), valida CSRF em todo POST e despacha para o controller conforme a rota.
- **Roteamento** — `backend/routes/web.php` mapeia o parâmetro `?page=` para `[Controller, método]`.
- **Controllers** — recebem a requisição, orquestram a lógica e renderizam a view. Estendem `BaseController` (render, redirect, flash, JSON, `requireAuth`, PRG).
- **Services** — regra de negócio reutilizável (ex.: `CadastroRestService`).
- **DAO / DAOImpl** — contratos (`DAOs/`) e implementações PDO (`DAOImpl/`) de acesso a dados.
- **Models** — entidades que encapsulam operações de domínio (ex.: `Agendamento`, `ControleChave`).
- **Helpers** — utilitários (`Auth`, `Csrf`, `Upload`, `HorarioHelper`, `EnsalamentoHelper`).
- **Views** — templates PHP organizados por perfil, com layout unificado (`layouts/painel_open.php` e `painel_close.php`).

---

## Estrutura de diretórios

```text
labhubuniceplac/
├── public/                      # Document root (único ponto público)
│   ├── index.php                # Front Controller
│   ├── .htaccess                # Rewrite → index.php
│   ├── assets/                  # css/, js/, images/, sounds/
│   └── uploads/                 # Fotos de perfil enviadas
│
├── backend/
│   ├── config/                  # app.php (constantes, .env, timezone) e database.php (PDO)
│   ├── routes/web.php           # Tabela de rotas
│   ├── controllers/             # Auth, Professor, Coordenador, Suporte, Api, Cadastro, Base
│   ├── services/                # CadastroRestService
│   ├── models/                  # Agendamento, ControleChave, ChamadoSuporte, Laboratorio, Disciplina
│   ├── DAOs/                    # Interfaces (contratos de persistência)
│   ├── DAOImpl/                 # Implementações PDO dos DAOs
│   ├── helpers/                 # Auth, Csrf, Upload, HorarioHelper, EnsalamentoHelper
│   └── database/                # setup.php, seed.php, seed_bi.php, migrate.php
│
├── frontend/
│   └── views/                   # auth/, professor/, coordenador/, suporte/, shared/, layouts/
│
├── docker/entrypoint.sh         # Bootstrap do container (espera DB, migra, seed)
├── composer.json / composer.lock
├── .env.example
└── README.md
```

---

## Funcionalidades por perfil

### 👨‍🏫 Professor
- Dashboard com aulas do dia e atalhos.
- Solicitação de reserva de laboratório (com verificação de choque de horário).
- Calendário de aulas e reservas.
- Consulta de horários disponíveis e do próprio ensalamento.
- Histórico e acompanhamento do status das solicitações.
- Registro de **retirada de chave** (validado por horário/turno e posse da reserva).
- **SOS** (aula em andamento) e abertura de **chamado técnico** ao suporte.

### 🧑‍💼 Coordenação
- Aprovação/rejeição de solicitações de reserva.
- Gestão do **Quadro de Horários** semanal (aulas fixas) e **Kanban** de aulas (drag-and-drop entre dias).
- **Ensalamento** (distribuição de turmas por sala/turno).
- Cadastros: cursos, semestres, blocos, andares, salas, laboratórios, disciplinas, professores.
- Calendário consolidado (reservas avulsas + grade fixa + feriados).
- **Relatórios BI**: KPIs e gráficos de ocupação, carga por professor, uso por laboratório e horas por curso, com exportação CSV.

### 🛠️ Suporte Técnico
- **Mapa diário** de ocupação dos laboratórios (filtrável por data).
- Controle de **chaves**: recebimento/devolução, atrasos e histórico.
- **Chamados** técnicos: fila de pendentes em tempo real (polling a cada 8s) com **alerta sonoro** ao chegar chamado novo, e histórico de atendimentos.
- Mapa de ensalamento e relação de laboratórios.

---

## Modelo de dados

Banco `sistema_labs` (MySQL/InnoDB, `utf8mb4`). Tabelas principais:

| Tabela | Descrição |
|---|---|
| `usuarios` | Contas e perfis (professor/coordenador/suporte), foto, verificação |
| `laboratorios` | Laboratórios e capacidade |
| `disciplinas` | Disciplinas |
| `cursos` | Cursos |
| `semestres` | Semestres/turmas |
| `blocos` → `andares` → `salas` | Hierarquia física dos espaços |
| `agendamentos` | Reservas avulsas (pendente/aprovado/rejeitado/cancelado) |
| `chamados_suporte` | Chamados técnicos (pendente/resolvido) |
| `controle_chaves` | Retirada/devolução de chaves (em_uso/devolvido) |
| `quadros_horarios` | Versões do quadro de horários (período letivo) |
| `quadro_aulas` | Aulas fixas da grade (alimenta o BI) |
| `ensalamento` | Alocação de turmas em salas por turno |

As relações usam chaves estrangeiras com políticas explícitas (`CASCADE`, `RESTRICT`, `SET NULL`). O schema completo está em [`backend/database/setup.php`](backend/database/setup.php) e detalhado no PDF.

---

## Rotas

| `?page=` | Controller → método | Descrição |
|---|---|---|
| `login` | `AuthController::login` | Login por e-mail/senha |
| `cadastro` | `AuthController::cadastro` | Cadastro de professor |
| `verificar` | `AuthController::verificar` | Verificação de e-mail por token |
| `google` | `AuthController::google` | Login via Google OAuth |
| `logout` | `AuthController::logout` | Encerra a sessão |
| `professor` | `ProfessorController::index` | Painel do professor |
| `coordenador` | `CoordenadorController::index` | Painel da coordenação |
| `kanban` | `CoordenadorController::kanban` | Mover aula (AJAX) |
| `suporte` | `SuporteController::index` | Painel do suporte |
| `api/check-sos` | `ApiController::checkSos` | Contagem de chamados (JSON) |
| `api/check-sos-status` | `ApiController::checkSosStatus` | Chamados pendentes + HTML (JSON) |
| `api/andares` | `ApiController::andaresPorBloco` | Andares de um bloco (JSON) |
| `api/salas` | `ApiController::salasPorAndar` | Salas de um andar (JSON) |

---

## Segurança

- **Senhas** com hash `bcrypt` (`password_hash` / `password_verify`).
- **Proteção CSRF** — token por sessão validado em **toda** requisição POST (corpo `_csrf` ou header `X-CSRF-Token`); injeção automática nos formulários via `assets/js/app.js`.
- **Sessão endurecida** — cookie `HttpOnly`, `SameSite=Lax`, `Secure` sob HTTPS, e `session_regenerate_id` no login.
- **Controle de acesso por perfil** — `requireAuth()` protege as rotas dos painéis.
- **Cadastro institucional** — e-mail `@uniceplac.edu.br` obrigatório; senha mínima de 8 caracteres.
- **Login Google** — opcionalmente restrito a um domínio (`GOOGLE_ALLOWED_DOMAIN`).
- **PRG (Post/Redirect/Get)** — evita reenvio de ações ao recarregar.
- **`.htaccess`** — desativa listagem de diretórios e roteia tudo pelo front controller.

---

## Pré-requisitos

- PHP 8.2+ (com extensão `pdo_mysql`)
- Composer
- MySQL 8.x — recomendado via Docker
- Docker (opcional, para o banco)

---

## Instalação e execução

### 1. Dependências
```bash
composer install
```

### 2. Variáveis de ambiente
```bash
cp .env.example .env
# edite o .env com as credenciais do banco e do Google OAuth
```

### 3. Banco de dados (via Docker)
```bash
docker run --name labhub-mysql \
  -e MYSQL_ROOT_PASSWORD=labhub123 \
  -e MYSQL_DATABASE=sistema_labs \
  -p 3307:3306 -d mysql:8.4
```
> No `.env`, use `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_USER=root`, `DB_PASS=labhub123`.

### 4. Criar schema e popular dados
```bash
php backend/database/setup.php     # cria/recria as tabelas
php backend/database/seed.php      # dados base + usuários demo
php backend/database/seed_bi.php   # quadro de demonstração para os Relatórios BI
php backend/database/migrate.php   # migrações incrementais (idempotente)
```

### 5. Subir a aplicação
```bash
php -S localhost:8000 -t public
```
Acesse **http://localhost:8000**.

> **Observação:** ao rodar fora do Docker (com `php -S`), execute `php backend/database/migrate.php` após o `setup` para aplicar migrações incrementais — o entrypoint do Docker já faz isso automaticamente.

---

## Variáveis de ambiente

| Variável | Descrição |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexão MySQL |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Credenciais OAuth |
| `GOOGLE_REDIRECT_URI` | URI de retorno (vazio = derivado do host atual) |
| `GOOGLE_ALLOWED_DOMAIN` | Restringe login Google a um domínio (vazio = qualquer) |
| `APP_DEBUG` | `true` mostra erros na tela; `false` apenas em log (produção) |

---

## Scripts de banco de dados

| Script | Função |
|---|---|
| `setup.php` | Cria/recria todo o schema (⚠️ apaga as tabelas existentes) |
| `seed.php` | Insere usuários demo, laboratórios, cursos, disciplinas, semestres, blocos/andares/salas |
| `seed_bi.php` | Recria um quadro **"Grade Demonstração BI"** com aulas variadas para validar os Relatórios BI (idempotente) |
| `migrate.php` | Migrações incrementais seguras de rodar várias vezes |

---

## Usuários de demonstração

Criados pelo `seed.php` — senha **`12345678`** para todos:

| Perfil | E-mail |
|---|---|
| Coordenador | `coordenador@uniceplac.edu.br` |
| Suporte | `suporte@uniceplac.edu.br` |
| Professor | `professor@uniceplac.edu.br` |

> Novos cadastros pela tela são criados já com o e-mail verificado e perfil **professor**.

---

## Melhorias futuras

- Notificações em tempo real (WebSocket) no lugar do polling.
- Envio efetivo de e-mail de verificação (PHPMailer já disponível).
- Dashboard analítico ampliado e relatórios avançados.
- Integração com o sistema acadêmico e calendário institucional.
- Aplicativo mobile e acesso por QR Code.

---

## Licença

Projeto acadêmico desenvolvido para fins educacionais e institucionais — UNICEPLAC.
