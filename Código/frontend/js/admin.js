/**
 * MecOn - admin.js
 * Hub administrativo: apenas confirma que o usuário logado é admin
 * e exibe seu nome. As ações reais ficam nas sub-páginas.
 */
const admin = exigirAdminUI();
if (admin) {
    document.getElementById('nome-admin').textContent = admin.nome;
}
