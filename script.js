console.log('PubMed2020 script.js loaded');
function copyLinkedInText(text) {

console.log('PubMed2020 script.js GO');
const decoded = text
            .replace(/&quot;/g, '\"')
            .replace(/&#039;/g, "\'")
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>');
        
    if (navigator.clipboard) {
        navigator.clipboard.writeText(decoded).then(() => {
            // Message de confirmation
            const msg = document.createElement('div');
            msg.textContent = '✓ Texte copié ! Collez-le dans LinkedIn.';
            msg.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
            document.body.appendChild(msg);
            setTimeout(() => msg.remove(), 3000);
        }).catch(() => {
            alert('Erreur de copie. Copiez manuellement le texte.');
        });
    }
}
