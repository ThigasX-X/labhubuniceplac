# LabHub UNICEPLAC

Sistema web desenvolvido para gerenciamento de laboratórios acadêmicos do UNICEPLAC, permitindo o controle centralizado de reservas, ensalamento, suporte técnico e utilização dos espaços laboratoriais.

---

# Objetivo

O LabHub foi criado para modernizar e centralizar os processos relacionados à utilização dos laboratórios da instituição, reduzindo conflitos de agendamento, facilitando o controle operacional e melhorando a comunicação entre professores, coordenação e equipe de suporte.

---

# Funcionalidades

## Autenticação e Controle de Acesso

* Login com credenciais institucionais
* Cadastro de usuários
* Verificação de e-mail
* Recuperação e validação de acesso
* Integração com autenticação Google
* Controle de permissões por perfil

---

## Professor

* Solicitação de reservas de laboratório
* Visualização de calendário
* Consulta de horários disponíveis
* Histórico de solicitações
* Acompanhamento do status das reservas
* Consulta de ensalamento

---

## Coordenação

* Aprovação ou rejeição de solicitações
* Gestão de reservas
* Gerenciamento de disciplinas
* Administração de laboratórios
* Visualização de quadro de horários
* Gestão de pendências
* Relatórios gerenciais
* Kanban para acompanhamento de demandas

---

## Equipe de Suporte

* Controle de chaves dos laboratórios
* Registro de empréstimos e devoluções
* Gestão de chamados técnicos
* Histórico de atendimentos
* Mapa diário de utilização
* Monitoramento operacional dos laboratórios

---

# Arquitetura do Projeto

O sistema segue uma estrutura inspirada no padrão MVC (Model-View-Controller).

```text
LabHub
│
├── backend
│   ├── controllers
│   ├── models
│   ├── DAOs
│   ├── DAOImplementations
│   ├── services
│   ├── helpers
│   ├── config
│   └── routes
│
├── frontend
│   ├── views
│   ├── assets
│   └── layouts
│
├── vendor
└── database
```

---

# Tecnologias Utilizadas

## Back-end

* PHP 8.2
* PDO
* Arquitetura MVC

## Banco de Dados

* MySQL

## Front-end

* HTML5
* CSS3
* JavaScript

## Bibliotecas

* Google API Client
* PHPMailer
* Monolog
* PHP Dotenv

## Controle de Versão

* Git
* GitHub

---

# Fluxo de Utilização

## Professor

1. Realiza login no sistema
2. Consulta disponibilidade dos laboratórios
3. Solicita uma reserva
4. Aguarda aprovação da coordenação
5. Utiliza o laboratório na data agendada

## Coordenação

1. Analisa solicitações
2. Aprova ou rejeita reservas
3. Gerencia laboratórios e disciplinas
4. Acompanha indicadores e relatórios

## Suporte

1. Recebe solicitações operacionais
2. Controla entrega e devolução de chaves
3. Registra ocorrências
4. Acompanha histórico de atendimentos

---

# Principais Módulos

## Agendamentos

Responsável pelo gerenciamento das reservas de laboratórios.

## Quadro de Horários

Organiza e apresenta a ocupação dos laboratórios.

## Ensalamento

Permite visualizar a distribuição das turmas e disciplinas.

## Controle de Chaves

Gerencia retirada e devolução das chaves dos laboratórios.

## Chamados de Suporte

Centraliza solicitações técnicas e acompanhamento de atendimento.

## Relatórios

Fornece informações estratégicas para tomada de decisão.

---

# Segurança

* Senhas armazenadas com hash seguro
* Verificação de e-mail institucional
* Controle de sessão
* Controle de acesso baseado em perfil
* Proteção de rotas autenticadas

---

# Instalação

## Pré-requisitos

* PHP 8.2 ou superior
* MySQL
* Composer
* Servidor Apache ou Nginx

## Clonar o Projeto

```bash
git clone https://github.com/seu-usuario/labhub.git
```

## Instalar Dependências

```bash
composer install
```

## Configurar Variáveis de Ambiente

Copie o arquivo:

```bash
.env.example
```

para:

```bash
.env
```

Configure:

```env
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

## Configurar Banco de Dados

Execute os scripts presentes na pasta:

```text
backend/database
```

## Executar o Projeto

Configure o servidor web apontando para a raiz da aplicação.

---

# Possíveis Melhorias Futuras

* Notificações em tempo real
* Integração com sistema acadêmico
* Dashboard analítico
* Aplicativo mobile
* QR Code para acesso aos laboratórios
* Relatórios avançados
* Integração com calendário institucional

---
# Licença

Projeto acadêmico desenvolvido para fins educacionais e institucionais.
