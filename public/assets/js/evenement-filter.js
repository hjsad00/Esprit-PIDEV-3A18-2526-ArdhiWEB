/**
 * Handles dynamic filtering for the events list.
 * Listen to input/change on filter fields and updates the grid via AJAX.
 */
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('event-filter-form');
    const searchInput = filterForm.querySelector('input[name="search"]');
    const typeSelect = filterForm.querySelector('select[name="type"]');
    const statutSelect = filterForm.querySelector('select[name="statut"]');
    const eventsGrid = document.getElementById('events-grid');

    let debounceTimer;

    const performFilter = () => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);

        // Add X-Requested-With header to trigger the AJAX response from Symfony
        fetch(`${filterForm.action.replace('/evenement', '/evenement/filter')}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            eventsGrid.innerHTML = html;
        })
        .catch(error => {
            console.error('Error during filtering:', error);
        });
    };

    // Listen for typing in the search box (with debounce)
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performFilter, 400);
    });

    // Listen for selection changes
    typeSelect.addEventListener('change', performFilter);
    statutSelect.addEventListener('change', performFilter);

    // Prevent default form submission if someone hits Enter
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        performFilter();
    });
});
