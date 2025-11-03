// expenses-filters.js
function initExpensesFilters() {
    // Initialisation des variables
    const expensesTableBody = document.getElementById('expensesTableBody');
    const expensesCount = document.getElementById('expensesCount');
    const noExpensesMessage = document.getElementById('noExpensesMessage');
    const filterCategory = document.getElementById('filterCategory');
    const filterDateStart = document.getElementById('filterDateStart');
    const filterDateEnd = document.getElementById('filterDateEnd');
    const filterAmountMin = document.getElementById('filterAmountMin');
    const filterAmountMax = document.getElementById('filterAmountMax');
    const applyFilters = document.getElementById('applyFilters');
    const resetFilters = document.getElementById('resetFilters');
    const sortButtons = document.querySelectorAll('.sort-btn');

    if (!expensesTableBody || !expensesCount || !noExpensesMessage || !filterCategory || !filterDateStart || !filterDateEnd || !filterAmountMin || !filterAmountMax || !applyFilters || !resetFilters || !sortButtons) {
        console.warn('One or more required elements for expenses filters are missing.');
        return;
    }
    
    // Données des dépenses
    let expensesData = [];
    const expenseRows = expensesTableBody.querySelectorAll('tr');
    
    expenseRows.forEach(row => {
        expensesData.push({
            element: row,
            date: row.querySelector('td[data-sort]').getAttribute('data-sort'),
            amount: parseFloat(row.querySelector('td.text-end').getAttribute('data-sort')),
            category: row.querySelector('td:nth-child(2)').textContent.trim(),
            description: row.querySelector('td:nth-child(3)').textContent.trim(),
            dateText: row.querySelector('td:first-child').textContent.trim()
        });
    });
    
    // Fonction pour appliquer les filtres
    function applyExpensesFilters() {
        const categoryFilter = filterCategory.value.toLowerCase();
        const dateStartFilter = filterDateStart.value ? new Date(filterDateStart.value) : null;
        const dateEndFilter = filterDateEnd.value ? new Date(filterDateEnd.value) : null;
        const amountMinFilter = filterAmountMin.value ? parseFloat(filterAmountMin.value) : null;
        const amountMaxFilter = filterAmountMax.value ? parseFloat(filterAmountMax.value) : null;
        
        let visibleCount = 0;
        
        expensesData.forEach(expense => {
            let isVisible = true;
            
            // Filtre par catégorie
            if (categoryFilter && expense.category.toLowerCase() !== categoryFilter) {
                isVisible = false;
            }
            
            // Filtre par date
            if (dateStartFilter || dateEndFilter) {
                const expenseDate = new Date(parseInt(expense.date) * 1000);

                if (dateStartFilter && expenseDate < dateStartFilter) {
                    isVisible = false;
                }
                if (dateEndFilter && expenseDate > new Date(dateEndFilter.getTime() + 86400000)) { // +1 jour
                    isVisible = false;
                }
            }
            
            // Filtre par montant
            if (amountMinFilter && expense.amount < amountMinFilter) {
                isVisible = false;
            }
            if (amountMaxFilter && expense.amount > amountMaxFilter) {
                isVisible = false;
            }
            
            // Afficher ou masquer la ligne
            expense.element.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });
        
        // Mettre à jour le compteur
        expensesCount.textContent = `${visibleCount} dépense(s)`;
        
        // Afficher le message si aucune dépense
        noExpensesMessage.classList.toggle('d-none', visibleCount > 0);
    }
    
    // Fonction pour trier les dépenses
    function sortExpenses(sortType) {
        const visibleRows = expensesData.filter(expense => 
            expense.element.style.display !== 'none'
        );
        
        visibleRows.sort((a, b) => {
            switch(sortType) {
                case 'date-desc':
                    return b.date - a.date;
                    
                case 'date-asc':
                    return a.date - b.date;
                    
                case 'amount-desc':
                    return b.amount - a.amount;
                    
                case 'amount-asc':
                    return a.amount - b.amount;
                    
                case 'category':
                    return a.category.localeCompare(b.category);
                    
                case 'category-desc':
                    return b.category.localeCompare(a.category);
                    
                default:
                    return 0;
            }
        });
        
        // Réorganiser les lignes dans le tableau
        const tbody = expensesTableBody;
        
        // Vider le tableau temporairement
        tbody.innerHTML = '';
        
        // Ajouter les lignes triées
        visibleRows.forEach(expense => {
            tbody.appendChild(expense.element);
        });
        
        // Ajouter les lignes masquées à la fin
        expensesData.forEach(expense => {
            if (expense.element.style.display === 'none') {
                tbody.appendChild(expense.element);
            }
        });
    }
    
    // Événements
    console.log('Attaching event listener to applyFilters');
    applyFilters.addEventListener('click', function() {
        console.log('Apply filters button clicked');
        applyExpensesFilters();
    });
    
    resetFilters.addEventListener('click', function() {
        filterCategory.value = '';
        filterDateStart.value = '';
        filterDateEnd.value = '';
        filterAmountMin.value = '';
        filterAmountMax.value = '';
        applyExpensesFilters();
    });
    
    sortButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const sortType = this.getAttribute('data-sort');
            sortExpenses(sortType);
            
            // Mettre à jour l'interface pour indiquer le tri actif
            sortButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Initialisation
    applyExpensesFilters();
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser uniquement quand l'onglet Dépenses est activé
    const expensesTab = document.getElementById('expenses-tab');
    if (!expensesTab) {
        console.warn('Expenses tab element not found.');
        return;
    }
    expensesTab.addEventListener('shown.bs.tab', function (event) {
        initExpensesFilters();
    });

    // Si l'onglet Dépenses est actif au chargement, initialiser directement
    if (expensesTab.classList.contains('active')) {
        initExpensesFilters();
    }
});
