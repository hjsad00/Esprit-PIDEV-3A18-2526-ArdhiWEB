#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ROI Agricultural Engine
Moteur de calcul ROI avancé pour analyse financière agricole
"""

import json
import sys
import io
import math

# Force UTF-8 output encoding for Windows compatibility
if sys.stdout.encoding != 'utf-8':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

class RoiEngine:
    """Moteur de calcul ROI intelligent avec simulations financières"""
    
    def __init__(self, data):
        self.data = data
        self.cultures = {
            'Tomate': {'rendement_base': 50, 'prix': 5, 'coeff_clim': 1.1},
            'Piment': {'rendement_base': 40, 'prix': 6.5, 'coeff_clim': 1.05},
            'Olivier': {'rendement_base': 25, 'prix': 8, 'coeff_clim': 0.95},
            'Blé': {'rendement_base': 30, 'prix': 3.5, 'coeff_clim': 0.85},
        }
    
    def calculate_climate_factor(self, temperature=25, humidity=60, rain=0, wind_speed=0, jours_canicule=0, jours_pluie=0, jours_gel=0):
        """
        Calcule le facteur climatique basé sur les données météo réelles
        Utilise la température, humidité, pluie et vent de l'API OpenWeather
        
        Paramètres:
        - temperature: Température actuelle en °C (idéale: 20-30°C)
        - humidity: Humidité en % (idéale: 60-80%)
        - rain: Pluie en mm (pénalité si > 10mm)
        - wind_speed: Vitesse du vent en m/s (pénalité si > 5 m/s)
        - jours_canicule: Jours de canicule estimés (fallback)
        - jours_pluie: Jours de pluie estimés (fallback)
        - jours_gel: Jours de gel estimés (fallback)
        """
        factor = 1.00
        
        # Analyse basée sur les données RÉELLES de l'API OpenWeather
        
        # 1. Température
        if temperature > 35:  # Canicule
            factor -= 0.08
        elif temperature < 0:  # Gel
            factor -= 0.12
        elif temperature < 10:  # Froid
            factor -= 0.05
        elif temperature > 30:  # Chaud
            factor -= 0.03
        
        # 2. Humidité (trop sec ou trop humide nuit)
        if humidity < 40:  # Trop sec
            factor -= 0.04
        elif humidity > 85:  # Trop humide
            factor -= 0.05
        
        # 3. Pluie excessive
        if rain > 10:  # Plus de 10mm
            factor -= 0.06
        elif rain > 5:  # Entre 5-10mm
            factor -= 0.02
        
        # 4. Vent fort
        if wind_speed > 5:  # Plus de 5 m/s
            factor -= 0.04
        
        # Fallback: utiliser les jours si données réelles non disponibles
        if temperature == 25 and humidity == 60:  # Valeurs par défaut
            factor -= 0.03 * jours_canicule
            factor -= 0.02 * jours_pluie
            factor -= 0.04 * jours_gel
        
        # Limiter entre 0.50 et 1.20
        return max(0.50, min(1.20, factor))
    
    def calculate_roi(self):
        """Calcule tous les paramètres ROI"""
        
        # 1. FACTEUR CLIMATIQUE (basé sur données réelles OpenWeather ou fallback)
        facteur_climatique = self.calculate_climate_factor(
            temperature=self.data.get('temperature', 25),
            humidity=self.data.get('humidity', 60),
            rain=self.data.get('rain', 0),
            wind_speed=self.data.get('wind_speed', 0),
            jours_canicule=self.data.get('jours_canicule', 0),
            jours_pluie=self.data.get('jours_pluie', 0),
            jours_gel=self.data.get('jours_gel', 0)
        )
        
        # 2. COÛT TOTAL
        cout_total = (
            self.data.get('cout_semences', 0) +
            self.data.get('cout_engrais', 0) +
            self.data.get('cout_main_oeuvre', 0) +
            self.data.get('cout_irrigation', 0) +
            self.data.get('autres_couts', 0)
        )
        
        # 3. PRODUCTION
        surface = self.data.get('surface', 0)
        rendement = self.data.get('rendement', 0)
        production = surface * rendement * facteur_climatique
        
        # 4. REVENU
        prix_vente = self.data.get('prix_vente', 0)
        revenu = production * prix_vente
        
        # 5. MARGE
        marge = revenu - cout_total
        
        # 6. ROI %
        roi = (marge / cout_total * 100) if cout_total > 0 else 0
        
        # 7. CAPACITÉ PRÊT
        capacite_pret = marge * 0.60
        
        # 8. NIVEAU DE RENTABILITÉ
        if roi > 40:
            niveau = "Très rentable"
            emoji = "🔥"
        elif roi > 20:
            niveau = "Rentable"
            emoji = "🟢"
        elif roi > 0:
            niveau = "Moyen"
            emoji = "🟡"
        else:
            niveau = "Risque élevé"
            emoji = "🔴"
        
        # 9. RISQUE
        if facteur_climatique < 0.60:
            risque = "Élevé"
        elif facteur_climatique < 0.80:
            risque = "Modéré"
        else:
            risque = "Faible"
        
        # 10. CONSEILS AUTOMATIQUES
        conseils = self.generate_advices(
            cout_total, 
            marge, 
            roi, 
            facteur_climatique,
            self.data.get('jours_canicule', 0)
        )
        
        # 11. ALTERNATIVE CULTURE
        alternative = self.find_best_alternative()
        
        return {
            'production': round(production, 2),
            'revenu': round(revenu, 2),
            'cout_total': round(cout_total, 2),
            'marge': round(marge, 2),
            'roi': round(roi, 2),
            'capacite_pret': round(capacite_pret, 2),
            'facteur_climatique': round(facteur_climatique, 3),
            'niveau': niveau,
            'emoji': emoji,
            'risque': risque,
            'conseils': conseils,
            'alternative': alternative,
            'success': True
        }
    
    def generate_advices(self, cout_total, marge, roi, facteur_clim, jours_canicule):
        """Génère conseils intelligents basés sur l'analyse"""
        conseils = []
        
        # Coût engrais
        if self.data.get('cout_engrais', 0) > cout_total * 0.3:
            conseils.append("💡 Réduire les dépenses en engrais (trop élevées)")
        
        # Coût irrigation
        if self.data.get('cout_irrigation', 0) > cout_total * 0.2:
            conseils.append("💧 Optimiser la consommation d'eau")
        
        # Canicule
        if jours_canicule > 5:
            conseils.append("📅 Reporter la plantation en période moins chaude")
        
        # ROI faible
        if roi < 15:
            conseils.append("⚠️ Envisager une culture alternative ou réduire la surface")
        
        # Facteur climatique
        if facteur_clim >= 0.95:
            conseils.append("✅ Excellente période agricole, conditions optimales")
        
        # Marge positive
        if marge > 0:
            conseils.append("✔️ Projet viable et profitable")
        
        return conseils if conseils else ["✅ Conditions favorables observées"]
    
    def find_best_alternative(self):
        """Teste les cultures alternatives et retourne la meilleure"""
        culture_actuelle = self.data.get('culture', 'Tomate')
        meilleure = None
        meilleur_roi = -999
        
        for culture, params in self.cultures.items():
            if culture == culture_actuelle:
                continue
            
            # Adaptation du rendement à la culture alternative
            rendement_alternatif = params['rendement_base'] * params['coeff_clim']
            production_alt = self.data.get('surface', 0) * rendement_alternatif
            revenu_alt = production_alt * params['prix']
            cout = self.data.get('cout_semences', 0) + \
                   self.data.get('cout_engrais', 0) + \
                   self.data.get('cout_main_oeuvre', 0) + \
                   self.data.get('cout_irrigation', 0) + \
                   self.data.get('autres_couts', 0)
            
            marge_alt = revenu_alt - cout
            roi_alt = (marge_alt / cout * 100) if cout > 0 else 0
            
            if roi_alt > meilleur_roi:
                meilleur_roi = roi_alt
                meilleure = culture
        
        roi_actuel = (self.data.get('prix_vente', 0) * 
                     self.data.get('surface', 0) * 
                     self.data.get('rendement', 0) - 
                     (self.data.get('cout_semences', 0) + 
                      self.data.get('cout_engrais', 0) + 
                      self.data.get('cout_main_oeuvre', 0) + 
                      self.data.get('cout_irrigation', 0) + 
                      self.data.get('autres_couts', 0))) / \
                     (self.data.get('cout_semences', 0) + 
                      self.data.get('cout_engrais', 0) + 
                      self.data.get('cout_main_oeuvre', 0) + 
                      self.data.get('cout_irrigation', 0) + 
                      self.data.get('autres_couts', 0) or 1) * 100
        
        # Retourner l'alternative seulement si elle est meilleure de >5%
        if meilleur_roi > roi_actuel + 5:
            return f"{meilleure} (+{round(meilleur_roi - roi_actuel, 1)}%)"
        
        return "Maintenir la culture actuelle"


def main():
    """Fonction principale - lit JSON, calcule, retourne résultat"""
    try:
        # Lire les données JSON depuis stdin
        input_data = sys.stdin.read()
        data = json.loads(input_data)
        
        # Calculer ROI
        engine = RoiEngine(data)
        result = engine.calculate_roi()
        
        # Retourner le résultat JSON
        print(json.dumps(result, ensure_ascii=False, indent=2))
        
    except json.JSONDecodeError as e:
        print(json.dumps({
            'success': False,
            'error': f'Erreur JSON: {str(e)}'
        }))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': f'Erreur moteur: {str(e)}'
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()
