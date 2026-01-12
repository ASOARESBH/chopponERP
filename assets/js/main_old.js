console.log('Chopp On Tap - Sistema carregado com sucesso! 🍺');

// ========================================
// Funções Globais - Menu Lateral
// ========================================
document.addEventListener("DOMContentLoaded", function () {
    const menuToggle = document.getElementById("menu-toggle");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener("click", () => {
            sidebar.classList.toggle("active");
            overlay.classList.toggle("active");
        });

        overlay.addEventListener("click", () => {
            sidebar.classList.remove("active");
            overlay.classList.remove("active");
        });
    }
});

// ========================================
// FUNÇÕES DE MODAL (CORREÇÃO)
// ========================================

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.classList.add('active');

    // Fechar ao clicar fora
    function closeOnOutsideClick(e) {
        if (e.target === modal) {
            closeModal(id);
            modal.removeEventListener('click', closeOnOutsideClick);
        }
    }
    modal.addEventListener('click', closeOnOutsideClick);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.classList.remove('active');
}


// ========================================
// Funções de Confirmação
// ========================================

function confirmDelete(msg, callback) {
    if (confirm(msg)) {
        callback();
    }
}

function deleteRecord(url) {
    if (confirm("Tem certeza que deseja excluir este registro?")) {
        window.location.href = url;
    }
}

// ========================================
// Dashboard - Carregar via AJAX
// ========================================

function loadDashboardCards() {
    fetch("dashboard_data.php")
        .then(response => response.json())
        .then(data => {
            document.getElementById("cardTaps").innerText = data.total_taps;
            document.getElementById("cardConsumo").innerText = data.total_consumo;
            document.getElementById("cardClientes").innerText = data.total_clientes;
            document.getElementById("cardFaturamento").innerText = "R$ " + data.total_faturamento;
        })
        .catch(err => console.error("Erro ao carregar cards:", err));
}

// Se existir container dos cards, carrega
if (document.getElementById("cardTaps")) {
    loadDashboardCards();
}


// ========================================
// Tratamento de formulários genéricos
// ========================================

document.querySelectorAll("form.ajax").forEach(form => {
    form.addEventListener("submit", function (event) {
        event.preventDefault();

        const formData = new FormData(this);

        fetch(this.action, {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        })
        .catch(e => alert("Erro no envio do formulário."));
    });
});

// ========================================
// Sistema de Tabs (caso exista)
// ========================================
document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', function () {
        const target = this.dataset.target;

        document.querySelectorAll('.tab-btn')
            .forEach(btn => btn.classList.remove('active'));

        document.querySelectorAll('.tab-content')
            .forEach(tab => tab.classList.remove('active'));

        this.classList.add('active');
        document.getElementById(target).classList.add('active');
    });
});

// ========================================
// Funções adicionais específicas do sistema
// ========================================

// Tratamento para inputs numéricos
document.querySelectorAll("input[data-type='number']").forEach(input => {
    input.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "");
    });
});

// Máscara CPF
function mascaraCPF(campo) {
    let v = campo.value.replace(/\D/g, '');
    if (v.length <= 11) {
        campo.value = v.replace(/(\d{3})(\d)/, "$1.$2")
                       .replace(/(\d{3})(\d)/, "$1.$2")
                       .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    }
}

// Evita erro caso o CPF não esteja presente
const cpfInput = document.getElementById("cpf");
if (cpfInput) {
    cpfInput.addEventListener("input", function () {
        mascaraCPF(cpfInput);
    });
}

