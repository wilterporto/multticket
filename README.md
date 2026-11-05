# Mult Ticket - Plugin para GLPI 10 & 11

**Autor:** Wilter Porto  
**Versão:** 1.0.0  
**Licença:** GPLv3+  
**Compatibilidade:** GLPI 10.0.0 até 11.9.99  

---

## 📌 Objetivo do Plugin

O plugin **Mult Ticket** permite o registro automatizado e consistente de múltiplos chamados/tickets no GLPI simultaneamente, gerando **exatamente um chamado independente para cada entidade selecionada**.

As entidades disponíveis para seleção no formulário são listadas **rigorosamente até o nível 2 da árvore hierárquica do GLPI**:
* **Nível 1:** Entidade Raiz.
* **Nível 2:** Subentidades diretas filhas da raiz.

---

## 🚀 Principais Recursos

1. **Abertura em Lote por Entidade:**
   * Preenchimento centralizado do formulário de chamado (Título, Descrição/Conteúdo, Tipo, Urgência, Impacto, Prioridade, Categoria ITIL, Requerente, Técnico Atribuído e Prazo de Solução).
   * Replicação para cada entidade selecionada com controle transacional e rastreabilidade individual.
   * Opção para interligar os chamados gerados no lote via vínculo nativo do GLPI (`Ticket_Ticket`).

2. **Filtro de Entidades até o Nível 2:**
   * Filtro nativo SQL com `level <= 2`.
   * Respeito às permissões multitenant e entidades ativas do usuário na sessão.
   * Ferramentas de seleção rápida: *Todas*, *Apenas Nível 1*, *Apenas Nível 2*, *Desmarcar Todas*.
   * Campo de busca em tempo real para filtragem instantânea de entidades.
   * Contador visual em tempo real de entidades selecionadas.

3. **Controle Estrito de Acesso por Perfil:**
   * O plugin adiciona uma aba dedicada chamada **Mult Ticket** na edição de cada perfil em **Administração > Perfis**.
   * Ao instalar, o direito `plugin_multticket` é propagado em todos os perfis do GLPI.
   * **Somente perfis com o direito "Criar" explicitamente concedido poderão visualizar o menu e registrar chamados.**
   * Acesso bloqueado automaticamente tanto na interface quanto nas camadas de controlador e modelo para perfis não autorizados.

4. **Módulo de Auditoria Multitenant (Exclusivo Superadmin):**
   * Registro detalhado de cada lote executado: usuário, perfil, data/hora, IP, quantidade de chamados, IDs dos chamados gerados e entidades afetadas.
   * **Paginação limitada estritamente a 30 registros por página**.
   * Filtros avançados por período, ação, entidade e busca textual.
   * **Exclusão Lógica (Soft Delete):** Nenhum registro de auditoria é excluído fisicamente (`is_deleted = 1`).
   * **Validação de Dependências:** O sistema valida antes da exclusão se existem dependências ativas (por exemplo, chamados do lote ainda ativos no GLPI).

5. **Interface e Usabilidade:**
   * Sinalização de todos os campos obrigatórios com **1 único asterisco na cor vermelha** (`*`).
   * No canto superior direito de cada tela, é exibido o nome do usuário com menu de acesso ao seu cadastro/perfil e, abaixo do nome, a data e hora do último acesso ao sistema.
   * Total compatibilidade com **Sidebar** e **Temas Claro e Escuro (Dark Mode)** do GLPI 10 e GLPI 11 (Tabler).
   * Opções estruturadas como **Enumeradores** (Tipos, Urgências, Impactos, Prioridades, Níveis e Ações de Auditoria).

6. **Práticas Rigorosas de Segurança:**
   * Proteção contra SQL Injection com Prepared Statements e Queries estruturadas do GLPI `$DB`.
   * Sanitização e validação de todos os dados no backend antes do processamento.
   * Proteção contra XSS com escape de saída (`Html::clean`, `htmlspecialchars`).
   * Proteção contra CSRF com validação de tokens em todas as requisições de alteração de estado (`Session::checkCSRF`).

---

## 📂 Estrutura de Arquivos

```
plugins/multticket/
├── css/
│   └── multticket.css            # Estilos, Dark Mode, asterisco vermelho e badges
├── front/
│   ├── audit.php                 # Tela de Auditoria exclusiva para Superadmin (30/página)
│   ├── config.php                # Tela de Configurações Gerais do Plugin
│   └── multticket.form.php       # Tela principal de criação de chamados múltiplos
├── inc/
│   ├── enum/
│   │   ├── auditaction.class.php   # Enumerador de Ações de Auditoria
│   │   ├── entitylevel.class.php   # Enumerador de Níveis Hierárquicos
│   │   ├── ticketimpact.class.php  # Enumerador de Níveis de Impacto
│   │   ├── ticketpriority.class.php# Enumerador de Prioridades
│   │   ├── tickettype.class.php    # Enumerador de Tipos (Incidente / Requisição)
│   │   └── ticketurgency.class.php # Enumerador de Urgências
│   ├── audit.class.php           # Modelo de Auditoria, soft delete e checagem de dependência
│   ├── config.class.php          # Gerenciamento de configurações por entidade
│   ├── entity.class.php          # Consulta e validação de entidades até o nível 2
│   ├── menu.class.php            # Menus do GLPI (Assistência e Superadmin)
│   ├── profile.class.php         # Aba e matriz de permissões no Perfil do usuário
│   └── ticket.class.php          # Processamento em lote de chamados
├── js/
│   └── multticket.js             # Seletores rápidos, filtro em tempo real e validação
├── hook.php                      # Criação/remoção de tabelas e ciclo de vida
├── setup.php                     # Definições, requisitos, versão e autor
└── README.md                     # Documentação técnica do plugin
```

---

## ⚙️ Instalação, Ativação e Configuração de Perfis

1. Acesse o GLPI como Administrador.
2. Navegue até **Configurar > Plugins**.
3. Localize o plugin **Mult Ticket - Chamados em Múltiplas Entidades** (Autor: Wilter Porto).
4. Clique em **Instalar** e, em seguida, em **Habilitar**.
5. Navegue até **Administração > Perfis** e abra os perfis desejados (ex: Técnico, Supervisor, etc.).
6. Clique na aba **Mult Ticket**, marque o direito **Criar chamados em múltiplas entidades** e clique em **Salvar**.
7. Somente os usuários associados aos perfis configurados terão acesso ao menu **Assistência > Chamados Múltiplos** para realizar os registros.

