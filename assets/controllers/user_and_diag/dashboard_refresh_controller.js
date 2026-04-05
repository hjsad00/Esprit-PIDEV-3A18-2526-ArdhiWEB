import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        "points", "level", "progress", "badges", "leaderboard",
        "weatherIcon", "weatherTemp", "weatherApparent", "weatherHumidity", "weatherWind", "weatherAdvice", "weatherBody",
        "alerts", "alertsBody", "predictiveRisks", "predictiveBody", "treatmentTiming"
    ];
    static values = {
        url: String,
        interval: Number,
        lastPoints: Number
    }

    connect() {
        this.interval = this.intervalValue || 10000; // Use 10s as default

        // Fetch immediately on load, then start polling
        this.fetchStats();
        this.startPolling();
    }

    disconnect() {
        this.stopPolling();
    }

    startPolling() {
        this.pollTimer = setInterval(() => this.fetchStats(), this.interval);
    }

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
        }
    }

    async fetchStats() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) return;

            const data = await response.json();

            // Still check points for general optimization, but we update everything if we fetch
            this.updateUI(data);
            this.lastPointsValue = data.points;
        } catch (e) {
            console.error("Dashboard refresh error:", e);
        }
    }

    toggleCollapse(event) {
        const header = event.currentTarget;
        const card = header.closest('.intell-card');
        const icon = header.querySelector('.toggle-icon');

        // Find the body div - it's the next sibling or children of siblings
        const body = card.querySelector('.weather-body, .alerts-body, .predict-body');

        if (body) {
            const isHidden = body.style.display === 'none';
            body.style.display = isHidden ? '' : 'none';
            if (icon) icon.textContent = isHidden ? '▲' : '▼';

            // Add a class for potential CSS transitions
            card.classList.toggle('is-collapsed', !isHidden);
        }
    }



    updateUI(data) {
        // Gamification
        if (this.hasPointsTarget) this.pointsTarget.textContent = `${data.points} pts`;
        if (this.hasLevelTarget) this.levelTarget.textContent = `Niveau ${data.level}`;
        if (this.hasProgressTarget) {
            this.progressTarget.style.width = `${data.progress}%`;
            this.progressTarget.setAttribute('aria-valuenow', data.progress);
        }

        if (this.hasBadgesTarget) {
            this.badgesTarget.innerHTML = data.badges.slice(0, 3).map(badge => `
                <div class="badge-item" title="${badge.description}">
                    <div class="icon">${badge.icon}</div>
                    <div class="name">${badge.name}</div>
                </div>
            `).join('');
        }

        if (this.hasLeaderboardTarget) {
            this.leaderboardTarget.innerHTML = data.leaderboard.map(player => `
                <div class="lb-row ${player.isMe ? 'is-me' : ''}">
                    <div class="lb-rank">
                        ${player.rank === 1 ? '🥇' : player.rank === 2 ? '🥈' : player.rank === 3 ? '🥉' : '#' + player.rank}
                    </div>
                    <div class="lb-name">${player.name}</div>
                    <div class="lb-score">${player.points} pts <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">(Niv. ${player.level})</span></div>
                </div>
            `).join('');
        }

        // Weather
        if (data.weather) {
            if (this.hasWeatherIconTarget) this.weatherIconTarget.textContent = data.weather.icon;
            if (this.hasWeatherTempTarget) this.weatherTempTarget.textContent = `${data.weather.temperature}°`;
            if (this.hasWeatherApparentTarget) this.weatherApparentTarget.textContent = data.weather.apparentTemperature;
            if (this.hasWeatherHumidityTarget) this.weatherHumidityTarget.textContent = `${data.weather.humidity}%`;
            if (this.hasWeatherWindTarget) this.weatherWindTarget.textContent = `${data.weather.windSpeed} km/h`;
            if (this.hasWeatherAdviceTarget) this.weatherAdviceTarget.textContent = data.weather.advice;
        }

        // Alerts & Risks
        if (data.diseaseAlerts && this.hasAlertsTarget) {
            this.alertsTarget.innerHTML = data.diseaseAlerts.length > 0
                ? data.diseaseAlerts.map(d => `
                    <div class="disease-row">
                        <div class="d-name"><span>${d.icon}</span> ${d.diseaseName}</div>
                        <div class="d-sev ${d.severityLevel === 'Élevé' || d.severityLevel === 'CRITICAL' ? 'high' : 'mod'}">${d.severityLevel}</div>
                    </div>
                `).join('')
                : `<p style="color: rgba(255,255,255,0.5); font-size: 0.75rem; text-align: center; margin: 10px 0;">Aucune alerte en cours dans votre zone.</p>`;
        }

        if (data.predictiveRisks && this.hasPredictiveRisksTarget) {
            this.predictiveRisksTarget.innerHTML = data.predictiveRisks.length > 0
                ? data.predictiveRisks.map(r => `
                    <div class="risk-item">
                        <div class="r-head"><span>${r.icon}</span> ${r.diseaseType} — ${r.riskLevel}</div>
                        <div class="r-desc">${r.reason}</div>
                        <div class="r-adv">💡 ${r.advice}</div>
                    </div>
                `).join('')
                : `<p style="color: rgba(255,255,255,0.5); font-size: 0.75rem; text-align: center; margin: 10px 0;">Faible risque détecté pour les 72h à venir.</p>`;
        }

        if (this.hasTreatmentTimingTarget && data.treatmentTiming) {
            this.treatmentTimingTarget.textContent = data.treatmentTiming;
        }
    }
}
