class QuantumUI {
    constructor() {
        this.init();
    }
    
    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.enhanceCards();
        });
    }
    
    enhanceCards() {
        const cards = document.querySelectorAll('.quantum-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-4px)';
            });
        });
    }
}

window.QuantumUI = new QuantumUI();
