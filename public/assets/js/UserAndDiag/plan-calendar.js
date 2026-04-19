/**
 * plan-calendar.js - FullCalendar Integration
 */
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');
    var apiUrl = document.getElementById('calendar-api-url').value;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        buttonText: {
            today: "Aujourd'hui",
            month: 'Mois',
            week: 'Semaine',
            list: 'Liste'
        },
        events: apiUrl,
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            showEventModal(info.event);
        },
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        },
        displayEventTime: false,
        height: '100%'
    });

    calendar.render();

    // Modal Logic
    var modalOverlay = document.getElementById('event-modal-overlay');
    var modalClose = document.getElementById('event-modal-close');

    function showEventModal(event) {
        var props = event.extendedProps;

        document.getElementById('modal-title').textContent = event.title;
        document.getElementById('modal-date').textContent = event.start.toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('modal-plan').textContent = props.planName;

        var statusMap = {
            'PENDING': 'En attente',
            'COMPLETED': 'Terminée',
            'MISSED': 'Manquée'
        };
        var typeMap = {
            'treatment': 'Traitement',
            'prevention': 'Prévention'
        };

        document.getElementById('modal-status').textContent = statusMap[props.status] || props.status;
        document.getElementById('modal-type').textContent = typeMap[props.type] || props.type;

        var btnLink = document.getElementById('modal-link');
        if (props.type === 'treatment') {
            btnLink.href = `/user-and-diag/treatment-plan/${props.planId}`;
            btnLink.textContent = 'Voir le Plan de Traitement';
        } else {
            btnLink.href = `/user-and-diag/prevention-plan/${props.planId}`;
            btnLink.textContent = 'Voir le Plan de Prévention';
        }

        modalOverlay.classList.add('active');
    }

    modalClose.addEventListener('click', function () {
        modalOverlay.classList.remove('active');
    });

    modalOverlay.addEventListener('click', function (e) {
        if (e.target === modalOverlay) {
            modalOverlay.classList.remove('active');
        }
    });
});
