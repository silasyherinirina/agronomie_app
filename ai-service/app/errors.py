"""
Erreurs structurées du service IA (cahier des charges §9) : le frontend ne doit jamais avoir à
deviner la cause d'un échec depuis un message en texte libre. Chaque erreur métier porte un
`code` stable ( UNKNOWN_CROP | TRAINING_REQUIRED | MODEL_INACTIVE | MODEL_NOT_FOUND |
MODEL_INVALID ), un statut HTTP et, quand elle concerne une culture précise, son `crop_key` —
voir le gestionnaire global dans app/main.py qui les sérialise en :

    {"success": false, "error": {"code": "...", "message": "...", "crop_key": "..."}}

jamais un 500 générique pour une absence de modèle parfaitement normale (§9 : "ne pas retourner
500 pour une absence normale de modèle").
"""


class AiServiceError(Exception):
    def __init__(self, code: str, message: str, status_code: int, crop_key: str | None = None) -> None:
        self.code = code
        self.message = message
        self.status_code = status_code
        self.crop_key = crop_key
        super().__init__(message)
