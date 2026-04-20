# 📋 Sprint Backlog - Utilisation du Sprint Python

**Projet**: ArdhiWEB - Module ROI Avancé
**Date**: 2026-04-19
**Équipe**: Backend (Python/Symfony)

---

## 📊 SPRINT BACKLOG - FORMAT USER STORY

### Sprint 1 - ROI Module MVP (TERMINÉ)

| User Story | Tâches | Estimation | Statut |
|---|---|---|---|
| **US-ROI-01** En tant que développeur backend, je peux créer un moteur de calcul ROI Python afin de calculer automatiquement la rentabilité des cultures avec facteur climatique | 1. Implémentation classe RoiEngine<br>2. Calculs financiers (production, revenu, marge)<br>3. Support 4 cultures (Tomate, Piment, Olivier, Blé)<br>4. Générateur recommandations intelligentes<br>5. Tests fonctionnels | 3 jours | ✅ Done |
| **US-ROI-02** En tant que développeur Symfony, je peux wrapper l'exécution Python afin d'intégrer le moteur dans le système | 1. Service PythonRoiService<br>2. Gestion process Symfony<br>3. Fallback PHP automatique<br>4. Logging erreurs<br>5. Timeout sécurisé (10s) | 2 jours | ✅ Done |
| **US-ROI-03** En tant que développeur BD, je peux créer l'infrastructure pour stocker l'historique ROI afin de tracker les analyses | 1. Entity RoiAnalyse<br>2. Repository queries<br>3. Migration Doctrine<br>4. Index BD<br>5. Validations | 2 jours | ✅ Done |

### Sprint 2 - Frontend Premium (TERMINÉ)

| User Story | Tâches | Estimation | Statut |
|---|---|---|---|
| **US-ROI-04** En tant qu'agriculteur, je peux analyser mon ROI via une interface AJAX afin d'obtenir résultats en temps réel | 1. Endpoint REST `/farmer/roi/analyze`<br>2. Validation JSON<br>3. Stockage BD automatique<br>4. Error handling<br>5. JsonResponse | 1.5 jours | ✅ Done |
| **US-ROI-05** En tant qu'utilisateur, je peux voir des résultats premium avec animations afin d'avoir meilleure UX | 1. Classe RoiAnalyzer JS<br>2. Intercept formulaire<br>3. POST AJAX<br>4. Display loader<br>5. Affichage résultats premium | 2 jours | ✅ Done |
| **US-ROI-06** En tant que designer, je peux appliquer styles premium afin que l'interface soit attractive | 1. CSS animations (fadeIn, slideIn)<br>2. Cartes premium dégradés<br>3. Hover effects<br>4. Dark mode<br>5. Responsive design | 1.5 jours | ✅ Done |

### Sprint 3 - Qualité & Performance (À COMMENCER)

| User Story | Tâches | Estimation | Statut |
|---|---|---|---|
| **US-ROI-07** En tant que DevOps, je peux profiler le moteur Python afin d'optimiser performance | 1. Profiling avec cProfile<br>2. Identifier goulets<br>3. Optimiser queries<br>4. Réduire temps réponse < 500ms<br>5. Benchmark résultats | 2 jours | ⏳ To Do |
| **US-ROI-08** En tant que développeur, je peux mettre en cache les résultats afin de réduire charge serveur | 1. Setup Redis/cache layer<br>2. Cache résultats climatiques<br>3. TTL 1h par parcelle<br>4. Invalidation stratégique<br>5. Metrics cache hit | 2 jours | ⏳ To Do |
| **US-ROI-09** En tant que QA, je peux tester le moteur Python afin de garantir qualité | 1. Structure pytest<br>2. Tests unitaires (4 cultures)<br>3. Edge cases (0, négatif)<br>4. Coverage > 80%<br>5. CI/CD integration | 3 jours | ⏳ To Do |
| **US-ROI-10** En tant que développeur, je peux tester l'intégration Symfony-Python afin de valider système | 1. Tests intégration Service<br>2. Mock Python process<br>3. Test fallback PHP<br>4. Test timeout<br>5. Error scenarios | 2 jours | ⏳ To Do |
| **US-ROI-11** En tant qu'administrateur, je peux avoir error handling robuste afin de maintenir stabilité | 1. Try/catch Python amélioré<br>2. Validation input stricte<br>3. Fallback robuste<br>4. Logging détaillé<br>5. Alerte admin si divergence | 2 jours | ⏳ To Do |

### Sprint 4 - Intelligence Avancée (PLANIFIÉ)

| User Story | Tâches | Estimation | Statut |
|---|---|---|---|
| **US-ROI-12** En tant qu'agriculteur, je peux voir données météo réelles afin d'avoir analyses plus précises | 1. Intégration API météo externe<br>2. Parsing données climat<br>3. Caching stratégique<br>4. Fallback données statiques<br>5. Update automatique | 3 jours | 📋 Planned |
| **US-ROI-13** En tant qu'agriculteur, je peux voir prédictions rendement ML afin d'anticiper récoltes | 1. Setup sklearn/ML<br>2. Feature engineering<br>3. Training data prep<br>4. Model deployment<br>5. Accuracy validation > 85% | 4 jours | 📋 Planned |
| **US-ROI-14** En tant que financier, je peux calculer NPV/IRR afin d'analyser investissements | 1. Algorithmes NPV/IRR<br>2. Cashflow projections 5 ans<br>3. Sensibilité paramètres<br>4. Rapports détaillés<br>5. Visualisations | 3 jours | 📋 Planned |

### Sprint 5 - Intégrations Externes (PLANIFIÉ)

| User Story | Tâches | Estimation | Statut |
|---|---|---|---|
| **US-ROI-15** En tant qu'agriculteur, je peux exporter rapport PDF afin d'avoir document professionnel | 1. Setup reportlab<br>2. Template rapport<br>3. Graphiques embedded<br>4. Signature agriculteur<br>5. Téléchargement | 2 jours | 📋 Planned |
| **US-ROI-16** En tant que banquier, je peux intégrer système afin d'évaluer capacité emprunt | 1. API prêt agricole<br>2. Scoring emprunt<br>3. Conditions tarifaires<br>4. Validation banques<br>5. Simulation prêt | 4 jours | 📋 Planned |
| **US-ROI-17** En tant qu'agriculteur mobile, je peux accéder API afin d'utiliser sur smartphone | 1. REST API mobile<br>2. Authentication JWT<br>3. Rate limiting<br>4. Sync offline<br>5. Conflict resolution | 3 jours | 📋 Planned |

---

## ✅ Sprint Actuel - Tâches Complétées



---

## 📊 Résumé Global

| Sprint | Nom | US Count | Jours | Priorité | Statut |
|--------|-----|----------|-------|----------|--------|
| S1 | MVP ROI Module | 3 | 7 | HAUTE | ✅ DONE |
| S2 | Frontend Premium | 3 | 4.5 | HAUTE | ✅ DONE |
| S3 | Qualité & Performance | 5 | 11 | HAUTE | ⏳ TO DO |
| S4 | Intelligence Avancée | 3 | 10 | MOYENNE | 📋 PLANNED |
| S5 | Intégrations Externes | 3 | 9 | BASSE | 📋 PLANNED |
| | **TOTAL** | **17** | **41.5** | | |

---

## 📋 Historique des Sprints

### Sprint 1: ROI Module MVP (TERMINÉ ✅)
- **US-ROI-01**: Moteur Python ROI (3 jours)
- **US-ROI-02**: Service Wrapper Symfony (2 jours)
- **US-ROI-03**: Infrastructure BD (2 jours)
- **Total**: 7 jours | **Résultat**: Module fonctionnel production-ready

### Sprint 2: Frontend Premium (TERMINÉ ✅)
- **US-ROI-04**: Endpoint AJAX REST (1.5 jours)
- **US-ROI-05**: Interface Premium JS (2 jours)
- **US-ROI-06**: Styles CSS Premium (1.5 jours)
- **Total**: 4.5 jours | **Résultat**: UX optimisée, prête utilisateurs

---

## 🔧 Dépendances Techniques

### Python Packages Actuels
- json, datetime, os (stdlib)

### Python Packages Recommandés (Futurs)
- pytest ≥ 7.0 (Testing)
- numpy, pandas (Data analysis)
- scikit-learn (ML - S4)
- requests (API météo - S4)
- reportlab (PDF - S5)
- plotly (Visualizations - S5)

### Symfony Services Actuels
- Process (execution Python)
- LoggerInterface (logging)
- EntityManager (BD)

---

## 🎯 Métriques de Succès

### Performance (Sprint 3)
- ✅ Temps réponse < 500ms
- ✅ Cache hit rate > 70%
- ✅ Test coverage > 80%

### Qualité (Sprint 3)
- ✅ Bugs critiques = 0
- ✅ Erreurs silent < 1/jour
- ✅ Convergence Python/PHP < 5%

### Adoption (Sprint 4-5)
- 🎯 80% utilisateurs actifs
- 🎯 NPS > 40
- 🎯 Churn rate < 5%

---

## 📅 Timeline Estimé

```
Sprint 3 (Qualité):     ████████ 2 semaines
Sprint 4 (Intelligence): ██████████ 2-3 semaines  
Sprint 5 (Intégrations): ██████████ 2-3 semaines
─────────────────────────────────────────
Total Roadmap v2.0:     ██████████████████ 6-7 semaines
```

**Démarrage Sprint 3**: Semaine du 21 avril 2026
**Fin v2.0**: Mi-juin 2026

---

## 👥 Assignation Équipe (À Définir)
