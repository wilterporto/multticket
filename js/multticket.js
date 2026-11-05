/**
 * ---------------------------------------------------------------------
 * Mult Ticket - Plugin GLPI 10 & 11
 * Scripts de Interatividade e Seleção de Entidades
 * ---------------------------------------------------------------------
 * @author Wilter Porto
 * ---------------------------------------------------------------------
 */

document.addEventListener('DOMContentLoaded', function () {
    const entityCheckboxes = document.querySelectorAll('.multticket-entity-checkbox');
    const countDisplay = document.getElementById('multticket-selected-count');
    const searchInput = document.getElementById('multticket-entity-search');
    const form = document.getElementById('multticket-form');

    // Atualiza o contador de entidades selecionadas
    function updateSelectedCount() {
        if (!countDisplay) return;
        const checkedCount = document.querySelectorAll('.multticket-entity-checkbox:checked').length;
        countDisplay.textContent = checkedCount;

        const badge = countDisplay.closest('.badge');
        if (badge) {
            if (checkedCount === 0) {
                badge.className = 'badge bg-danger-lt multticket-counter-badge';
            } else {
                badge.className = 'badge bg-success-lt multticket-counter-badge';
            }
        }
    }

    // Ouvinte em cada checkbox
    entityCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });

    // Botão: Selecionar Todas as Entidades Visíveis
    const btnSelectAll = document.getElementById('btn-select-all');
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            entityCheckboxes.forEach(cb => {
                const row = cb.closest('.multticket-entity-row');
                if (!row || row.style.display !== 'none') {
                    cb.checked = true;
                }
            });
            updateSelectedCount();
        });
    }

    // Botão: Desmarcar Todas
    const btnDeselectAll = document.getElementById('btn-deselect-all');
    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function () {
            entityCheckboxes.forEach(cb => {
                cb.checked = false;
            });
            updateSelectedCount();
        });
    }

    // Botão: Selecionar Apenas Nível 1 (Raiz)
    const btnSelectL1 = document.getElementById('btn-select-level-1');
    if (btnSelectL1) {
        btnSelectL1.addEventListener('click', function () {
            entityCheckboxes.forEach(cb => {
                const level = parseInt(cb.getAttribute('data-level'), 10);
                cb.checked = (level === 1);
            });
            updateSelectedCount();
        });
    }

    // Botão: Selecionar Apenas Nível 2 (Filhas)
    const btnSelectL2 = document.getElementById('btn-select-level-2');
    if (btnSelectL2) {
        btnSelectL2.addEventListener('click', function () {
            entityCheckboxes.forEach(cb => {
                const level = parseInt(cb.getAttribute('data-level'), 10);
                cb.checked = (level === 2);
            });
            updateSelectedCount();
        });
    }

    // Filtro em tempo real de pesquisa de entidades
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.multticket-entity-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Validação antes do envio do formulário
    if (form) {
        form.addEventListener('submit', function (e) {
            const checkedCount = document.querySelectorAll('.multticket-entity-checkbox:checked').length;
            const errorAlert = document.getElementById('multticket-validation-alert');

            if (checkedCount === 0) {
                e.preventDefault();
                if (errorAlert) {
                    errorAlert.classList.remove('d-none');
                    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    alert('Por favor, selecione ao menos uma entidade para registrar os chamados.');
                }
            } else {
                if (errorAlert) {
                    errorAlert.classList.add('d-none');
                }
                const submitBtn = document.getElementById('btn-submit-multticket');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processando chamados...';
                }
            }
        });
    }

    // Inicializa contador
    updateSelectedCount();
});

