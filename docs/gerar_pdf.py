#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Gera a documentação técnica completa do LabHub UNICEPLAC em PDF.
Uso: python3 docs/gerar_pdf.py
Saída: docs/LabHub-Documentacao.pdf
"""
import os
from datetime import date

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm, mm
from reportlab.platypus import (BaseDocTemplate, Frame, Image, ListFlowable,
                                ListItem, NextPageTemplate, PageBreak,
                                PageTemplate, Paragraph, Preformatted, Spacer,
                                Table, TableStyle)

BASE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(BASE)
LOGO = os.path.join(ROOT, "public/assets/images/uniceplac2.png")
OUT = os.path.join(BASE, "LabHub-Documentacao.pdf")

# ---------------------------------------------------------------- paleta / cores
VERDE = colors.HexColor("#00734F")     # verde institucional
LARANJA = colors.HexColor("#f07f3c")   # laranja institucional
CINZA = colors.HexColor("#4b5563")
CINZA_CLARO = colors.HexColor("#f1f5f9")
CODE_BG = colors.HexColor("#0f172a")
CODE_FG = colors.HexColor("#e2e8f0")
HEAD_BG = VERDE

# ---------------------------------------------------------------- estilos
ss = getSampleStyleSheet()
H1 = ParagraphStyle("H1", parent=ss["Heading1"], fontName="Helvetica-Bold",
                    fontSize=17, textColor=VERDE, spaceBefore=18, spaceAfter=8,
                    leading=21)
H2 = ParagraphStyle("H2", parent=ss["Heading2"], fontName="Helvetica-Bold",
                    fontSize=12.5, textColor=colors.HexColor("#0f5132"),
                    spaceBefore=12, spaceAfter=5, leading=16)
H3 = ParagraphStyle("H3", parent=ss["Heading3"], fontName="Helvetica-Bold",
                    fontSize=10.5, textColor=CINZA, spaceBefore=8, spaceAfter=3)
BODY = ParagraphStyle("Body", parent=ss["BodyText"], fontName="Helvetica",
                      fontSize=9.6, leading=14.5, alignment=TA_JUSTIFY,
                      spaceAfter=6, textColor=colors.HexColor("#1f2937"))
BULLET = ParagraphStyle("Bullet", parent=BODY, alignment=TA_LEFT, spaceAfter=2)
CODE = ParagraphStyle("Code", parent=ss["Code"], fontName="Courier",
                      fontSize=8.1, leading=11.2, textColor=CODE_FG,
                      backColor=CODE_BG, borderPadding=(7, 7, 7, 7),
                      spaceBefore=4, spaceAfter=10, leftIndent=2, rightIndent=2)
CELL = ParagraphStyle("Cell", parent=BODY, fontSize=8.6, leading=11.5,
                      alignment=TA_LEFT, spaceAfter=0)
CELL_H = ParagraphStyle("CellH", parent=CELL, textColor=colors.white,
                        fontName="Helvetica-Bold")
NOTE = ParagraphStyle("Note", parent=BODY, fontSize=8.8, leading=12.5,
                      backColor=colors.HexColor("#fff7ed"), borderPadding=(6, 6, 6, 6),
                      borderColor=LARANJA, borderWidth=0.6, spaceBefore=2, spaceAfter=10)
TOC_IT = ParagraphStyle("Toc", parent=BODY, fontSize=10, leading=18, spaceAfter=0)
COVER_T = ParagraphStyle("CoverT", parent=ss["Title"], fontSize=30,
                         textColor=VERDE, alignment=TA_CENTER, leading=34)
COVER_S = ParagraphStyle("CoverS", parent=ss["Normal"], fontSize=13.5,
                         textColor=CINZA, alignment=TA_CENTER, leading=18)


def esc(t):
    return t.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def p(t, style=BODY):
    return Paragraph(t, style)


def code(t):
    return Preformatted(esc(t.strip("\n")), CODE)


def bullets(items):
    return ListFlowable(
        [ListItem(p(it, BULLET), leftIndent=10, value="•") for it in items],
        bulletType="bullet", bulletColor=VERDE, leftIndent=12, spaceAfter=8)


def table(rows, widths, header=True):
    data = []
    for r, row in enumerate(rows):
        line = []
        for cell in row:
            st = CELL_H if (header and r == 0) else CELL
            line.append(Paragraph(cell, st))
        data.append(line)
    t = Table(data, colWidths=widths, repeatRows=1 if header else 0)
    style = [
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#cbd5e1")),
        ("ROWBACKGROUNDS", (0, 1 if header else 0), (-1, -1),
         [colors.white, CINZA_CLARO]),
    ]
    if header:
        style += [("BACKGROUND", (0, 0), (-1, 0), HEAD_BG)]
    t.setStyle(TableStyle(style))
    return t


# ---------------------------------------------------------------- doc / templates
story = []
SECTIONS = []  # (numero, titulo) para o sumário


def section(num, title):
    SECTIONS.append((num, title))
    story.append(PageBreak())
    story.append(p(f"{num}. {title}", H1))


def header_footer(canvas, doc):
    canvas.saveState()
    w, h = A4
    # cabeçalho
    canvas.setFillColor(VERDE)
    canvas.rect(0, h - 1.15 * cm, w, 1.15 * cm, fill=1, stroke=0)
    canvas.setFillColor(colors.white)
    canvas.setFont("Helvetica-Bold", 9)
    canvas.drawString(2 * cm, h - 0.78 * cm, "LabHub UNICEPLAC")
    canvas.setFont("Helvetica", 8)
    canvas.drawRightString(w - 2 * cm, h - 0.78 * cm, "Documentação Técnica")
    # rodapé
    canvas.setStrokeColor(colors.HexColor("#cbd5e1"))
    canvas.line(2 * cm, 1.25 * cm, w - 2 * cm, 1.25 * cm)
    canvas.setFillColor(CINZA)
    canvas.setFont("Helvetica", 8)
    canvas.drawString(2 * cm, 0.8 * cm, "Central de Reservas de Laboratórios")
    canvas.drawRightString(w - 2 * cm, 0.8 * cm, f"Página {doc.page}")
    canvas.restoreState()


doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=2 * cm, rightMargin=2 * cm,
                      topMargin=1.7 * cm, bottomMargin=1.7 * cm,
                      title="LabHub UNICEPLAC — Documentação Técnica",
                      author="UNICEPLAC")
frame = Frame(doc.leftMargin, doc.bottomMargin,
              doc.width, doc.height, id="main")
cover_frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="cover")
doc.addPageTemplates([
    PageTemplate(id="cover", frames=[cover_frame]),
    PageTemplate(id="content", frames=[frame], onPage=header_footer),
])

# ================================================================ CAPA
story.append(Spacer(1, 3.5 * cm))
if os.path.exists(LOGO):
    img = Image(LOGO, width=9 * cm, height=5 * cm, kind="proportional")
    img.hAlign = "CENTER"
    story.append(img)
story.append(Spacer(1, 1.2 * cm))
story.append(p("LabHub UNICEPLAC", COVER_T))
story.append(Spacer(1, 0.3 * cm))
story.append(p("Central de Reservas e Gestão de Laboratórios Acadêmicos", COVER_S))
story.append(Spacer(1, 0.2 * cm))
story.append(p("Documentação Técnica Completa", COVER_S))
story.append(Spacer(1, 2.2 * cm))
meses = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho",
         "agosto", "setembro", "outubro", "novembro", "dezembro"]
hoje = date.today()
story.append(p(f"Versão 1.0 &nbsp;•&nbsp; {hoje.day} de {meses[hoje.month-1]} de {hoje.year}",
               ParagraphStyle("d", parent=COVER_S, fontSize=10)))
story.append(NextPageTemplate("content"))
story.append(PageBreak())

# ================================================================ SUMÁRIO
story.append(p("Sumário", H1))
# preenchido dinamicamente abaixo via placeholder
TOC_INDEX = len(story)
story.append(Spacer(1, 1))  # placeholder substituído no fim

# ================================================================ 1. INTRODUÇÃO
section("1", "Introdução e Objetivo")
story.append(p(
    "O <b>LabHub UNICEPLAC</b> é um sistema web para gestão centralizada dos laboratórios "
    "acadêmicos da instituição. Ele organiza desde a solicitação e aprovação de reservas até "
    "o quadro de horários semanal, o ensalamento de turmas, o controle de entrega de chaves, "
    "a abertura de chamados técnicos e a geração de relatórios analíticos (BI)."))
story.append(p("O sistema atende três perfis de usuário, cada um com seu próprio painel:"))
story.append(bullets([
    "<b>Professor</b> — solicita reservas, acompanha suas aulas e aciona o suporte.",
    "<b>Coordenação</b> — aprova reservas, monta o quadro de horários e analisa indicadores.",
    "<b>Suporte Técnico</b> — controla chaves, atende chamados e acompanha a ocupação diária.",
]))
story.append(p("<b>Objetivos principais:</b>"))
story.append(bullets([
    "Reduzir conflitos de agendamento com verificação automática de choque de horário.",
    "Centralizar a operação dos laboratórios numa única ferramenta.",
    "Melhorar a comunicação entre professores, coordenação e suporte.",
    "Fornecer indicadores de ocupação e uso para apoiar a tomada de decisão.",
]))

# ================================================================ 2. STACK
section("2", "Stack Tecnológico e Dependências")
story.append(p("O back-end é escrito em PHP puro seguindo o padrão MVC; o front-end usa HTML, "
               "CSS e JavaScript com bibliotecas via CDN. A persistência é em MySQL via PDO."))
story.append(table([
    ["Camada", "Tecnologia"],
    ["Linguagem", "PHP 8.2+ (validado em PHP 8.5)"],
    ["Banco de dados", "MySQL 8.x — PDO, charset utf8mb4, engine InnoDB"],
    ["Padrão arquitetural", "MVC + Front Controller, com camadas DAO e Service"],
    ["Front-end", "HTML5, CSS3, JavaScript (vanilla)"],
    ["UI / Componentes", "Bootstrap 5.3, Bootstrap Icons 1.11"],
    ["Gráficos (BI)", "Chart.js 4.4"],
    ["Calendário", "FullCalendar 6.1"],
    ["Autenticação", "Sessão PHP + Google OAuth 2.0"],
    ["Pacotes", "Composer"],
], [4.2 * cm, 11.3 * cm]))
story.append(p("<b>Dependências declaradas no <font face='Courier'>composer.json</font>:</b>", H3))
story.append(table([
    ["Pacote", "Versão", "Uso no projeto"],
    ["google/apiclient", "^2.15", "Login social via Google OAuth 2.0"],
    ["vlucas/phpdotenv", "^5.6", "Leitura de variáveis de ambiente do .env"],
    ["phpmailer/phpmailer", "^6.9", "Biblioteca de e-mail (disponível p/ verificação)"],
    ["monolog/monolog", "^2.9", "Biblioteca de logs (disponível)"],
], [4.6 * cm, 2.2 * cm, 8.7 * cm]))
story.append(NOTE_PH := p(
    "<b>Nota:</b> PHPMailer e Monolog estão declarados como dependências, mas o envio de "
    "e-mail e o logging estruturado não estão ativados no fluxo atual — ficam disponíveis "
    "para evolução futura.", NOTE))

# ================================================================ 3. ARQUITETURA
section("3", "Arquitetura")
story.append(p("A aplicação tem um <b>único ponto de entrada</b> público "
               "(<font face='Courier'>public/index.php</font>). Toda requisição passa por ele, "
               "que inicia a sessão, valida o token CSRF (em POST) e despacha para o controller "
               "correspondente segundo a tabela de rotas."))
story.append(p("Fluxo de uma requisição:", H2))
story.append(code(
    "Requisição HTTP (?page=...)\n"
    "      |\n"
    "      v\n"
    "public/index.php  -->  backend/routes/web.php   (page => [Controller, metodo])\n"
    "      |\n"
    "      v\n"
    " Controller  -->  Service / Model  -->  DAO (interface) --> DAOImpl (PDO)  -->  MySQL\n"
    "      |\n"
    "      v\n"
    "  View (frontend/views/*.php)  -->  HTML + Bootstrap + Chart.js / FullCalendar"))
story.append(p("Responsabilidades das camadas:", H2))
story.append(table([
    ["Camada", "Responsabilidade"],
    ["Front Controller", "Sessão, CSRF, roteamento e despacho (public/index.php)"],
    ["Rotas", "Mapa ?page= → [Controller, método] (backend/routes/web.php)"],
    ["Controllers", "Orquestram a requisição e renderizam a view; estendem BaseController"],
    ["Services", "Regra de negócio reutilizável (ex.: CadastroRestService)"],
    ["Models", "Operações de domínio (Agendamento, ControleChave, ChamadoSuporte...)"],
    ["DAO / DAOImpl", "Contratos e implementações PDO de acesso a dados"],
    ["Helpers", "Auth, Csrf, Upload, HorarioHelper, EnsalamentoHelper"],
    ["Views", "Templates PHP por perfil + layout unificado"],
], [3.6 * cm, 11.9 * cm]))
story.append(p("Padrões de projeto aplicados:", H2))
story.append(bullets([
    "<b>Front Controller</b> — um único arquivo trata todas as requisições.",
    "<b>MVC</b> — separação entre dados (Model/DAO), apresentação (View) e controle (Controller).",
    "<b>DAO + Interface</b> — desacopla o acesso a dados das implementações concretas.",
    "<b>PRG (Post/Redirect/Get)</b> — evita reenvio de formulários ao recarregar a página.",
    "<b>Injeção de dependência simples</b> — o PDO é injetado nos controllers e DAOs.",
]))

# ================================================================ 4. ESTRUTURA
section("4", "Estrutura de Diretórios")
story.append(code(
    "labhubuniceplac/\n"
    "|-- public/                     # Document root (unico ponto publico)\n"
    "|   |-- index.php               # Front Controller\n"
    "|   |-- .htaccess               # Rewrite -> index.php\n"
    "|   |-- assets/                 # css/ js/ images/ sounds/\n"
    "|   `-- uploads/                # Fotos de perfil enviadas\n"
    "|\n"
    "|-- backend/\n"
    "|   |-- config/                 # app.php (constantes/.env) e database.php (PDO)\n"
    "|   |-- routes/web.php          # Tabela de rotas\n"
    "|   |-- controllers/            # Auth, Professor, Coordenador, Suporte, Api, Cadastro\n"
    "|   |-- services/               # CadastroRestService\n"
    "|   |-- models/                 # Agendamento, ControleChave, ChamadoSuporte, ...\n"
    "|   |-- DAOs/                    # Interfaces (contratos)\n"
    "|   |-- DAOImpl/                 # Implementacoes PDO\n"
    "|   |-- helpers/                 # Auth, Csrf, Upload, HorarioHelper, EnsalamentoHelper\n"
    "|   `-- database/                # setup.php, seed.php, seed_bi.php, migrate.php\n"
    "|\n"
    "|-- frontend/views/             # auth/ professor/ coordenador/ suporte/ shared/ layouts/\n"
    "|-- docker/entrypoint.sh        # Bootstrap do container\n"
    "|-- composer.json / .lock\n"
    "|-- .env.example\n"
    "`-- README.md"))

# ================================================================ 5. CONFIGURAÇÃO
section("5", "Configuração")
story.append(p("backend/config/app.php", H2))
story.append(p("Carrega o autoload do Composer, lê o <font face='Courier'>.env</font> "
               "(via phpdotenv), define o fuso <font face='Courier'>America/Sao_Paulo</font>, "
               "ajusta o nível de erros e declara as constantes globais:"))
story.append(table([
    ["Constante", "Valor / Função"],
    ["ROOT_PATH / BACKEND_PATH / VIEWS_PATH", "Caminhos-base da aplicação"],
    ["APP_NAME", "\"UNICEPLAC - Central de Reservas\""],
    ["UPLOAD_DIR / UPLOAD_URL", "Diretório e URL das fotos de perfil"],
    ["DEFAULT_AVATAR", "Imagem padrão de avatar"],
    ["APP_DEBUG (env)", "Controla display_errors (tela vs. log)"],
], [6.7 * cm, 8.8 * cm]))
story.append(p("backend/config/database.php", H2))
story.append(p("Cria a conexão PDO com MySQL usando as variáveis do ambiente, define o fuso "
               "do banco para <font face='Courier'>-03:00</font>, ativa exceções "
               "(<font face='Courier'>ERRMODE_EXCEPTION</font>) e o fetch associativo por padrão. "
               "Em caso de falha, responde HTTP 500 com JSON de erro."))
story.append(code(
    "$pdo = new PDO(\"mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4\", $user, $pass);\n"
    "$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n"
    "$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);"))

# ================================================================ 6. ROTEAMENTO
section("6", "Roteamento")
story.append(p("O front controller lê <font face='Courier'>?page=</font>, consulta a tabela em "
               "<font face='Courier'>backend/routes/web.php</font> e instancia o controller. "
               "Rotas desconhecidas caem em <font face='Courier'>login</font>. O "
               "<font face='Courier'>.htaccess</font> reescreve URLs amigáveis para o front controller."))
story.append(table([
    ["Rota (?page=)", "Controller → método", "Descrição"],
    ["login", "AuthController::login", "Login por e-mail/senha"],
    ["cadastro", "AuthController::cadastro", "Cadastro de professor"],
    ["verificar", "AuthController::verificar", "Verificação de e-mail por token"],
    ["google", "AuthController::google", "Login via Google OAuth"],
    ["logout", "AuthController::logout", "Encerra a sessão"],
    ["professor", "ProfessorController::index", "Painel do professor"],
    ["coordenador", "CoordenadorController::index", "Painel da coordenação"],
    ["kanban", "CoordenadorController::kanban", "Mover aula (AJAX drag-drop)"],
    ["suporte", "SuporteController::index", "Painel do suporte"],
    ["api/check-sos", "ApiController::checkSos", "Contagem de chamados (JSON)"],
    ["api/check-sos-status", "ApiController::checkSosStatus", "Chamados pendentes + HTML (JSON)"],
    ["api/andares", "ApiController::andaresPorBloco", "Andares de um bloco (JSON)"],
    ["api/salas", "ApiController::salasPorAndar", "Salas de um andar (JSON)"],
], [3.6 * cm, 5.6 * cm, 6.3 * cm]))

# ================================================================ 7. AUTENTICAÇÃO
section("7", "Autenticação e Autorização")
story.append(p("Login por e-mail e senha", H2))
story.append(p("O <font face='Courier'>AuthController::login</font> busca o usuário por e-mail "
               "(<font face='Courier'>UsuarioDAOImpl::findByEmail</font>) e valida a senha com "
               "<font face='Courier'>password_verify</font>. Contas com "
               "<font face='Courier'>email_verificado = 0</font> são bloqueadas. No sucesso, "
               "<font face='Courier'>Auth::login</font> regenera o ID de sessão e o usuário é "
               "redirecionado ao painel do seu perfil."))
story.append(p("Cadastro", H2))
story.append(bullets([
    "Exige e-mail institucional terminando em <font face='Courier'>@uniceplac.edu.br</font>.",
    "Senha mínima de 8 caracteres e confirmação de senha.",
    "A senha é gravada com hash (<font face='Courier'>password_hash</font>).",
    "Novos usuários recebem o perfil <b>professor</b> e já entram com e-mail verificado.",
]))
story.append(p("Login com Google (OAuth 2.0)", H2))
story.append(p("O <font face='Courier'>AuthController::google</font> usa a biblioteca "
               "google/apiclient. A <font face='Courier'>redirect URI</font> é derivada do host "
               "atual (funciona em localhost ou atrás de túnel/proxy) ou fixada via "
               "<font face='Courier'>GOOGLE_REDIRECT_URI</font>. Opcionalmente restringe o acesso "
               "a um domínio (<font face='Courier'>GOOGLE_ALLOWED_DOMAIN</font>). Na volta, "
               "<font face='Courier'>upsertGoogle</font> cria ou atualiza o usuário."))
story.append(p("Verificação de e-mail", H2))
story.append(p("A rota <font face='Courier'>verificar</font> valida um "
               "<font face='Courier'>token_verificacao</font> e ativa a conta. O fluxo está "
               "presente como estrutura; no cadastro atual a conta já é criada ativa."))
story.append(p("Sessão e controle de acesso", H2))
story.append(p("O helper <font face='Courier'>Auth</font> centraliza sessão "
               "(<font face='Courier'>id, nome, perfil, foto</font>). O "
               "<font face='Courier'>BaseController::requireAuth($perfil)</font> protege cada "
               "painel: sem sessão → login; perfil errado → redireciona ao painel correto via "
               "<font face='Courier'>Auth::destinoAposLogin</font>."))
story.append(table([
    ["Perfil", "Painel padrão após login"],
    ["coordenador", "/index.php?page=coordenador"],
    ["suporte", "/index.php?page=suporte"],
    ["professor (default)", "/index.php?page=professor"],
], [5 * cm, 10.5 * cm]))

# ================================================================ 8. SEGURANÇA
section("8", "Segurança")
story.append(p("Proteção CSRF", H2))
story.append(p("O helper <font face='Courier'>Csrf</font> gera um token por sessão "
               "(<font face='Courier'>32 bytes aleatórios</font>). O front controller chama "
               "<font face='Courier'>Csrf::check()</font> em <b>toda</b> requisição POST, "
               "comparando o token da sessão com o enviado no corpo "
               "(<font face='Courier'>_csrf</font>) ou no header "
               "<font face='Courier'>X-CSRF-Token</font> com <font face='Courier'>hash_equals</font>. "
               "Token inválido encerra com HTTP 419."))
story.append(p("O token é exposto numa <font face='Courier'>&lt;meta name=\"csrf-token\"&gt;</font> "
               "e injetado automaticamente em todo formulário POST no momento do "
               "<font face='Courier'>submit</font> (via <font face='Courier'>assets/js/app.js</font>), "
               "cobrindo inclusive formulários inseridos dinamicamente (ex.: chamados via polling)."))
story.append(p("Demais controles", H2))
story.append(bullets([
    "<b>Senhas</b> com hash bcrypt (password_hash / password_verify).",
    "<b>Cookie de sessão</b> HttpOnly, SameSite=Lax e Secure sob HTTPS.",
    "<b>session_regenerate_id</b> no login (mitiga fixation).",
    "<b>requireAuth</b> com checagem de perfil em todas as rotas de painel.",
    "<b>Upload</b> restrito a jpg/jpeg/png/webp, com nome derivado do usuário+timestamp.",
    "<b>Validação de posse</b>: a retirada de chave confere se a reserva é do professor logado.",
    "<b>.htaccess</b> desativa listagem de diretórios (Options -Indexes).",
    "<b>APP_DEBUG=false</b> oculta erros da tela em produção (mantém no log).",
]))

# ================================================================ 9. MODELO DE DADOS
section("9", "Modelo de Dados")
story.append(p("Banco <font face='Courier'>sistema_labs</font> (MySQL/InnoDB, utf8mb4). "
               "O schema é criado por <font face='Courier'>backend/database/setup.php</font>."))
story.append(p("Tabelas e finalidade:", H2))
story.append(table([
    ["Tabela", "Finalidade", "Status / Enums"],
    ["usuarios", "Contas e perfis, foto, verificação", "perfil; email_verificado"],
    ["laboratorios", "Laboratórios e capacidade", "—"],
    ["disciplinas", "Disciplinas (nome único)", "—"],
    ["cursos", "Cursos (nome único)", "—"],
    ["semestres", "Semestres/turmas (nome único)", "—"],
    ["blocos / andares / salas", "Hierarquia física dos espaços", "—"],
    ["agendamentos", "Reservas avulsas", "pendente/aprovado/rejeitado/cancelado"],
    ["chamados_suporte", "Chamados técnicos", "pendente/resolvido"],
    ["controle_chaves", "Retirada/devolução de chaves", "em_uso/devolvido"],
    ["quadros_horarios", "Versões do quadro (período letivo)", "—"],
    ["quadro_aulas", "Aulas fixas da grade (alimenta o BI)", "turno; dia_semana"],
    ["ensalamento", "Alocação de turmas em salas", "turno"],
], [3.5 * cm, 6.6 * cm, 5.4 * cm]))
story.append(p("Relacionamentos principais (chaves estrangeiras):", H2))
story.append(bullets([
    "<font face='Courier'>blocos 1—N andares 1—N salas</font> (FK RESTRICT; nome único por pai).",
    "<font face='Courier'>agendamentos</font> → laboratorios, usuarios, disciplinas (RESTRICT); "
    "chave única (lab, data, turno, período) evita reserva duplicada.",
    "<font face='Courier'>quadro_aulas</font> → quadros_horarios (CASCADE), disciplinas/cursos/"
    "semestres (RESTRICT), professor/laboratório/sala (SET NULL).",
    "<font face='Courier'>ensalamento</font> → usuarios (CASCADE), sala (RESTRICT); "
    "única por (sala, turno).",
    "<font face='Courier'>chamados_suporte</font> → usuarios (CASCADE).",
    "<font face='Courier'>controle_chaves</font> — <b>sem FK</b> em id_agendamento, pois também "
    "guarda ids sintéticos (+1.000.000) das aulas da grade fixa.",
]))
story.append(p("Detalhe: tabela quadro_aulas", H2))
story.append(p("É o coração do quadro de horários e a fonte dos Relatórios BI. Campos relevantes:"))
story.append(table([
    ["Campo", "Tipo", "Observação"],
    ["id_quadro", "INT", "FK → quadros_horarios (CASCADE)"],
    ["id_professor", "INT NULL", "FK → usuarios (SET NULL); NULL = EAD"],
    ["id_laboratorio", "INT NULL", "FK → laboratorios (SET NULL)"],
    ["id_curso / id_semestre / id_disciplina", "INT", "FKs obrigatórias"],
    ["id_sala", "INT NULL", "FK → salas (SET NULL)"],
    ["turno", "ENUM", "Matutino/Vespertino/Noturno"],
    ["dia_semana", "VARCHAR", "Segunda … Sábado"],
    ["carga_horaria_total", "INT", "Horas semanais (base do BI por professor/curso)"],
    ["horas_laboratorio", "INT", "Horas em laboratório (base de ocupação)"],
], [5.6 * cm, 2.3 * cm, 7.6 * cm]))

# ================================================================ 10. MÓDULOS POR PERFIL
section("10", "Módulos Funcionais por Perfil")
story.append(p("Professor — ProfessorController::index", H2))
story.append(p("Concentra as ações do professor; cada ação é um POST tratado e seguido de "
               "redirect (PRG). Principais ações:"))
story.append(bullets([
    "<b>Solicitar reserva</b> — valida choque de horário (HorarioHelper::verificaChoque) "
    "antes de gravar; reserva duplicada (lab/dia/turno) é barrada pela chave única.",
    "<b>Retirada de chave</b> — confere posse da reserva e janela de horário do turno "
    "(Matutino 07:00–12:30, Vespertino 13:00–18:30, Noturno 18:00–23:00).",
    "<b>SOS</b> — abre chamado durante aula em andamento.",
    "<b>Chamado geral</b> — abre chamado informando local e descrição.",
    "<b>Foto de perfil</b> — upload validado.",
]))
story.append(p("Coordenação — CoordenadorController::index", H2))
story.append(bullets([
    "Aprovar/rejeitar solicitações e gerenciar reservas.",
    "Quadro de horários (CRUD de aulas) e Kanban (mover aula entre dias via AJAX → rota kanban).",
    "Ensalamento (alocação por sala/turno) com selects encadeados bloco→andar→sala (rotas api/andares e api/salas).",
    "Cadastros de cursos, semestres, blocos, andares, salas, laboratórios e disciplinas.",
    "Calendário consolidado (reservas + grade fixa + feriados).",
    "Relatórios BI (seção 11).",
]))
story.append(p("Suporte — SuporteController::index", H2))
story.append(bullets([
    "Mapa diário de ocupação por data (reservas aprovadas + grade fixa do dia).",
    "Controle de chaves: dar baixa (devolução), destaque de atrasos e histórico.",
    "Chamados: fila de pendentes atualizada por polling, com alerta sonoro (seção 13).",
    "Mapa de ensalamento e relação de laboratórios.",
]))

# ================================================================ 11. BI
section("11", "Relatórios BI (Business Intelligence)")
story.append(p("Calculados em <font face='Courier'>CoordenadorController::calcularRelatoriosBI()</font> "
               "a partir das aulas do <b>quadro selecionado</b> (<font face='Courier'>quadro_aulas</font>). "
               "Se não houver quadro, os indicadores ficam zerados."))
story.append(p("Indicadores (KPIs)", H2))
story.append(table([
    ["KPI", "Como é calculado"],
    ["Taxa de Ocupação", "uso total ÷ capacidade global × 100"],
    ["Horas Totais de Uso", "soma de horas_laboratorio de todos os labs"],
    ["Lab Mais Usado", "laboratório com maior soma de horas_laboratorio"],
    ["Lab Mais Ocioso", "laboratório com maior ociosidade (capacidade − uso)"],
], [4.6 * cm, 10.9 * cm]))
story.append(p("Capacidade do laboratório", H2))
story.append(p("A ocupação usa uma <b>janela operacional semanal</b> fixa de "
               "<font face='Courier'>60 horas</font> por laboratório (≈ 6 dias × 3 turnos). "
               "É uma medida de <b>tempo</b> — por isso não se usa a capacidade de assentos, "
               "que mede pessoas, não horas."))
story.append(p("Gráficos (Chart.js)", H2))
story.append(table([
    ["Gráfico", "Tipo", "Dados"],
    ["Carga por Professor", "Barras", "carga_horaria_total por professor (top 10)"],
    ["Horas por Curso", "Pizza", "carga_horaria_total agrupada por curso"],
    ["Uso de Laboratórios", "Barras", "horas_laboratorio por laboratório"],
], [4.8 * cm, 2.6 * cm, 8.1 * cm]))
story.append(p("Tabelas detalhadas", H2))
story.append(bullets([
    "<b>Relatório por Professor</b> — horas/semana, laboratório e sala principais.",
    "<b>Relatório por Laboratório</b> — horas de uso, horas ociosas e % de ocupação.",
    "Ambas exportáveis para <b>CSV</b> direto na interface.",
]))
story.append(p("Para validar a tela, o script <font face='Courier'>seed_bi.php</font> cria um "
               "quadro \"Grade Demonstração BI\" com aulas variadas (professores, cursos, labs "
               "e dias distintos), gerando distribuições visíveis em todos os gráficos.", NOTE))

# ================================================================ 12. CHAVES
section("12", "Controle de Chaves")
story.append(p("Gerencia a entrega e devolução das chaves dos laboratórios, registrando quem "
               "retirou, horário previsto e real de devolução e quem entregou/recebeu."))
story.append(p("Fluxo:", H2))
story.append(bullets([
    "O <b>professor</b> registra a retirada (validada por posse da reserva e janela de horário).",
    "Enquanto não devolvida, a chave fica <font face='Courier'>em_uso</font> e aparece no painel do suporte.",
    "Se passar do horário previsto, o cartão é marcado como <b>ATRASADO</b> (com atalho de WhatsApp para cobrança).",
    "O <b>suporte</b> dá baixa informando quem recebeu e a hora real de devolução.",
]))
story.append(p("Detalhe técnico — ids sintéticos", H2))
story.append(p("A coluna <font face='Courier'>id_agendamento</font> de "
               "<font face='Courier'>controle_chaves</font> guarda tanto o id de uma reserva "
               "avulsa quanto um <b>id sintético</b> de uma aula da grade fixa (o id da aula "
               "<font face='Courier'>+ 1.000.000</font>). Por isso a tabela não tem FK nessa "
               "coluna — ela referencia duas origens diferentes."))

# ================================================================ 13. CHAMADOS / BIPE
section("13", "Chamados e Alerta Sonoro")
story.append(p("Professores abrem chamados (gerais ou SOS) que entram em "
               "<font face='Courier'>chamados_suporte</font> com status "
               "<font face='Courier'>pendente</font>. O painel do suporte os exibe em tempo "
               "quase real."))
story.append(p("Polling", H2))
story.append(p("A cada 8 segundos o painel chama "
               "<font face='Courier'>api/check-sos-status</font>, que devolve a contagem de "
               "pendentes e o HTML dos cartões de chamado (montados por "
               "<font face='Courier'>ApiController::checkSosStatus</font>), atualizando a fila "
               "sem recarregar a página."))
story.append(p("Alerta sonoro", H2))
story.append(bullets([
    "Toca quando a contagem de chamados <b>aumenta</b> com o painel já aberto.",
    "O som fica em <font face='Courier'>public/assets/sounds/alerta-chamado.mp3</font> (local).",
    "Por política de autoplay dos navegadores, o áudio é \"destravado\" no primeiro clique/tecla "
    "do usuário; antes disso ele não pode tocar automaticamente.",
]))

# ================================================================ 14. HELPERS
section("14", "Helpers")
story.append(table([
    ["Helper", "Responsabilidade"],
    ["Auth", "Sessão do usuário: check, id, nome, perfil, foto, login, logout, destinoAposLogin"],
    ["Csrf", "Token por sessão: token(), field() e check() (valida POST, HTTP 419 se inválido)"],
    ["Upload", "Upload de foto (extensões permitidas, remove a anterior, gera nome único)"],
    ["HorarioHelper", "Converte turno+período em horários; verifica choque de reservas"],
    ["EnsalamentoHelper", "Monta o mapa centralizado de ensalamento (salas, turnos, professores)"],
], [3.6 * cm, 11.9 * cm]))
story.append(p("HorarioHelper — janelas de horário", H2))
story.append(table([
    ["Turno", "Faixa padrão (sem período específico)"],
    ["Matutino", "08:20 – 11:55"],
    ["Vespertino", "14:20 – 18:00"],
    ["Noturno", "19:20 – 22:50"],
], [5 * cm, 10.5 * cm]))

# ================================================================ 15. CAMADA DE DADOS
section("15", "Camada de Dados (DAO / Model)")
story.append(p("Cada agregado tem um contrato em <font face='Courier'>DAOs/</font> e uma "
               "implementação PDO em <font face='Courier'>DAOImpl/</font>. Os "
               "<font face='Courier'>models/</font> encapsulam operações de domínio que "
               "consomem os DAOs."))
story.append(table([
    ["DAO / Model", "Principais operações"],
    ["UsuarioDAO", "findByEmail, findById, create, listProfessores, updateFoto, upsertGoogle"],
    ["AgendamentoDAO", "solicitar/criar reserva, listar pendentes/confirmadas, histórico, status"],
    ["ControleChaveDAO", "registrarRetirada, darBaixa, emUso, historico, chavesPorProfessor"],
    ["ChamadoSuporteDAO", "abrir, resolver, pendentes, resolvidos, countPendentes"],
    ["QuadroHorarioDAO", "all, idAtivo, criar, duplicar, excluir, salvar/editar/mover/excluir aula"],
    ["LaboratorioDAO", "all, create, update, delete"],
    ["DisciplinaDAO", "all, create, update, excluir"],
], [4.4 * cm, 11.1 * cm]))

# ================================================================ 16. FRONTEND
section("16", "Front-end")
story.append(p("As views são templates PHP organizados por perfil "
               "(<font face='Courier'>auth/, professor/, coordenador/, suporte/, shared/</font>) "
               "sobre um layout unificado em <font face='Courier'>layouts/painel_open.php</font> "
               "e <font face='Courier'>painel_close.php</font>."))
story.append(p("Recursos estáticos (public/assets)", H2))
story.append(bullets([
    "<b>css/app.css</b> — estilos do tema institucional (verde/laranja).",
    "<b>js/app.js</b> — token CSRF global, injeção do _csrf no submit, troca de seções do painel, tema.",
    "<b>images/</b> — logos UNICEPLAC e ícone do Google.",
    "<b>sounds/alerta-chamado.mp3</b> — alerta sonoro de chamados.",
]))
story.append(p("Bibliotecas via CDN", H2))
story.append(bullets([
    "Bootstrap 5.3 e Bootstrap Icons 1.11 (UI).",
    "Chart.js 4.4 (gráficos do BI).",
    "FullCalendar 6.1 (calendários de professor e coordenação).",
]))

# ================================================================ 17. SCRIPTS DE BANCO
section("17", "Scripts de Banco de Dados")
story.append(table([
    ["Script", "Função"],
    ["setup.php", "Cria/recria TODO o schema (apaga tabelas existentes antes)"],
    ["seed.php", "Usuários demo + laboratórios, cursos, disciplinas, semestres, blocos/andares/salas"],
    ["seed_bi.php", "Recria o quadro \"Grade Demonstração BI\" com aulas variadas (idempotente)"],
    ["migrate.php", "Migrações incrementais idempotentes (ex.: coluna id_semestre)"],
], [3.4 * cm, 12.1 * cm]))
story.append(p("Ordem recomendada de execução:", H2))
story.append(code(
    "php backend/database/setup.php     # cria o schema\n"
    "php backend/database/seed.php      # dados base + usuarios demo\n"
    "php backend/database/seed_bi.php   # quadro de demonstracao do BI\n"
    "php backend/database/migrate.php   # migracoes incrementais"))

# ================================================================ 18. INSTALAÇÃO
section("18", "Instalação e Execução")
story.append(p("Pré-requisitos: PHP 8.2+ (com pdo_mysql), Composer, MySQL 8.x e, "
               "opcionalmente, Docker para o banco."))
story.append(p("1) Dependências e ambiente", H2))
story.append(code("composer install\ncp .env.example .env   # edite as credenciais"))
story.append(p("2) Banco via Docker", H2))
story.append(code(
    "docker run --name labhub-mysql \\\n"
    "  -e MYSQL_ROOT_PASSWORD=labhub123 \\\n"
    "  -e MYSQL_DATABASE=sistema_labs \\\n"
    "  -p 3307:3306 -d mysql:8.4"))
story.append(p("No .env: <font face='Courier'>DB_HOST=127.0.0.1</font>, "
               "<font face='Courier'>DB_PORT=3307</font>, "
               "<font face='Courier'>DB_USER=root</font>, "
               "<font face='Courier'>DB_PASS=labhub123</font>."))
story.append(p("3) Schema, dados e aplicação", H2))
story.append(code(
    "php backend/database/setup.php\n"
    "php backend/database/seed.php\n"
    "php backend/database/seed_bi.php\n"
    "php backend/database/migrate.php\n"
    "php -S localhost:8000 -t public     # acesse http://localhost:8000"))
story.append(p("Usuários de demonstração (senha 12345678):", H2))
story.append(table([
    ["Perfil", "E-mail"],
    ["Coordenador", "coordenador@uniceplac.edu.br"],
    ["Suporte", "suporte@uniceplac.edu.br"],
    ["Professor", "professor@uniceplac.edu.br"],
], [5 * cm, 10.5 * cm]))

# ================================================================ 19. TROUBLESHOOTING
section("19", "Operação e Troubleshooting")
story.append(table([
    ["Sintoma", "Causa / Solução"],
    ["Erro \"Unknown column id_semestre\"",
     "Banco antigo sem a migração. Rode php backend/database/migrate.php."],
    ["\"Sessão expirada ou requisição inválida\" (HTTP 419)",
     "Formulário POST sem token CSRF. Recarregue a página (o app.js injeta o _csrf no submit)."],
    ["Alerta sonoro não toca",
     "Política de autoplay: clique uma vez na página para destravar o áudio. Só toca em chamado novo."],
    ["Filtro de data joga para o login",
     "Form GET precisa do campo page=suporte (corrigido como hidden input)."],
    ["Front/back não abrem",
     "Suba o MySQL (container) e a aplicação (php -S). Confira credenciais no .env."],
], [5.8 * cm, 9.7 * cm]))

# ================================================================ 20. GLOSSÁRIO
section("20", "Glossário")
story.append(table([
    ["Termo", "Significado"],
    ["Ensalamento", "Distribuição/alocação de turmas e disciplinas em salas por turno"],
    ["Quadro de horários", "Grade semanal de aulas fixas de um período letivo"],
    ["Reserva avulsa", "Agendamento pontual de laboratório feito pelo professor"],
    ["Turno", "Período do dia: Matutino, Vespertino ou Noturno"],
    ["SOS", "Chamado aberto pelo professor durante uma aula em andamento"],
    ["BI", "Business Intelligence — indicadores e gráficos analíticos"],
    ["PRG", "Post/Redirect/Get — padrão que evita reenvio de formulários"],
    ["CSRF", "Cross-Site Request Forgery — ataque mitigado por token por sessão"],
    ["DAO", "Data Access Object — camada de acesso a dados"],
], [3.6 * cm, 11.9 * cm]))
story.append(Spacer(1, 0.6 * cm))
story.append(p("— Fim da documentação —",
               ParagraphStyle("end", parent=BODY, alignment=TA_CENTER, textColor=CINZA)))

# ---------------------------------------------------------------- monta o sumário
toc_flow = []
for num, title in SECTIONS:
    toc_flow.append(p(f"<b>{num}.</b>&nbsp;&nbsp;{esc(title)}", TOC_IT))
story[TOC_INDEX] = ListFlowable(
    [ListItem(f, leftIndent=0, value="") for f in toc_flow],
    bulletType="bullet", start="", bulletColor=colors.white, leftIndent=0)

doc.build(story)
print("PDF gerado em:", OUT)
