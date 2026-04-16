/**
 * Evenement Dynamic Filter Module
 * Handles real-time search and filtering via AJAX
 * Usage:
 *   EvenementFilter.init('form-id', '/api/endpoint', '#results-container');
 */
const EvenementFilter = {
  /**
   * Initialize filter form with AJAX support
   * @param {string} formId - ID of the form element
   * @param {string} endpoint - API endpoint for filtering
   * @param {string} resultsSelector - Selector for results container
   * @param {boolean} searchOnChange - Filter on change (default: true)
   */
  init: function(formId, endpoint, resultsSelector, searchOnChange = true) {
    const form = document.getElementById(formId);
    const resultsContainer = document.querySelector(resultsSelector);

    if (!form || !resultsContainer) {
      console.error('EvenementFilter: Form or results container not found');
      return;
    }

    // Get all form inputs that can trigger search
    const triggers = form.querySelectorAll('input[type="text"], select');
    const filterBtn = form.querySelector('[type="submit"]');
    const resetBtn = form.parentElement.querySelector('.btn-reset');

    // If searchOnChange is true, filter on each input change
    if (searchOnChange) {
      triggers.forEach(input => {
        if (input.type === 'text') {
          // For text input, add real-time search with debounce
          input.addEventListener('input', this.debounce(() => {
            this.submitFilter(form, endpoint, resultsContainer);
          }, 500));
          // Also listen for change event
          input.addEventListener('change', () => {
            this.submitFilter(form, endpoint, resultsContainer);
          });
        } else if (input.type === 'select-one' || input.tagName === 'SELECT') {
          // For selects, filter immediately on change
          input.addEventListener('change', () => {
            this.submitFilter(form, endpoint, resultsContainer);
          });
        }
      });
    }

    // Manual filter button (if exists)
    if (filterBtn) {
      filterBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.submitFilter(form, endpoint, resultsContainer);
      });
    }

    // Reset button (if exists)
    if (resetBtn) {
      resetBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.resetFilter(form, endpoint, resultsContainer);
      });
    }
  },

  /**
   * Submit filter form via AJAX
   */
  submitFilter: function(form, endpoint, resultsContainer) {
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    // Show loading indicator
    resultsContainer.style.opacity = '0.6';
    resultsContainer.style.pointerEvents = 'none';

    fetch(`${endpoint}?${params.toString()}`, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html',
      }
    })
      .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
      })
      .then(html => {
        // Replace results container content
        resultsContainer.innerHTML = html;
        // Re-initialize AOS for new elements
        if (typeof AOS !== 'undefined') {
          AOS.refresh();
        }
        // Fade in animation
        resultsContainer.style.opacity = '1';
        resultsContainer.style.pointerEvents = 'auto';
      })
      .catch(error => {
        console.error('Filter error:', error);
        resultsContainer.style.opacity = '1';
        resultsContainer.style.pointerEvents = 'auto';
      });
  },

  /**
   * Reset filter to default state
   */
  resetFilter: function(form, endpoint, resultsContainer) {
    // Clear all inputs
    form.querySelectorAll('input[type="text"], select').forEach(input => {
      if (input.type === 'text') {
        input.value = '';
      } else {
        input.value = '';
      }
    });

    // Trigger filter submission
    this.submitFilter(form, endpoint, resultsContainer);
  },

  /**
   * Debounce helper for real-time search
   */
  debounce: function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
};

// Auto-initialize filters on page load
document.addEventListener('DOMContentLoaded', function() {
  // Index (all events) - dynamic filtering
  if (document.getElementById('event-filter-form') && document.getElementById('events-grid')) {
    EvenementFilter.init('event-filter-form', '/evenement/filter', '#events-grid', true);
  }

  // Inscriptions - dynamic filtering
  if (document.getElementById('insc-filter-form') && document.getElementById('insc-grid')) {
    EvenementFilter.init('insc-filter-form', '/evenement/inscriptions/filter', '#insc-grid', true);
  }

  // Favoris - dynamic filtering
  if (document.getElementById('fav-filter-form') && document.getElementById('fav-grid')) {
    EvenementFilter.init('fav-filter-form', '/evenement/favoris/filter', '#fav-grid', true);
  }

  // Participations - dynamic filtering
  if (document.getElementById('part-filter-form') && document.getElementById('part-grid')) {
    EvenementFilter.init('part-filter-form', '/evenement/participations/filter', '#part-grid', true);
  }
});
