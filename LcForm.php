<?php

/* ==============================================================================
  LcForm V-1.2 by Loquicom <contact@loquicom.fr>

  GPL-3.0
  LcForm.php
  ============================================================================ */

/**
 * Constante pour indiquer le tableau a utiliser
 */
define('FORM_REQUEST', 0);
define('FORM_POST', 1);
define('FORM_GET', 2);

/**
 * Constante pour la verification des valeurs
 */
define('CHECK_STRING', 0);
define('CHECK_INT', 1);
define('CHECK_FLOAT', 2);
define('CHECK_DOUBLE', 2);
define('CHECK_EMAIL', 3);
define('CHECK_URL', 4);
define('CHECK_DATE', 5);
define('CHECK_PHONE', 6);
define('CHECK_ARRAY', 7);
define('CHECK_REQUIRED', 8);

class LcForm {

    /**
     * Indique si l'on protege ou non les champs de l'injection XML
     * @var boolean
     */
    private static $protect = true;

    /**
     * Indique si l'on determine le mimetype par le fichier ou l'extension
     * @var boolean
     */
    private static $mime_file = true;

    /**
     * Le message d'erreur lors de l'upload
     * @var string
     */
    private $upload_err = '';

    /**
     * Active la protection des string contre l'injextion XML
     */
    public function protect() {
        self::$protect = true;
    }

    /**
     * Desactive la protection des string contre l'injextion XML
     */
    public function unprotect() {
        self::$protect = false;
    }

    /* ----- Récupération des valeurs ----- */

    /**
     * Accède à une valeur dans un des tableaux
     * Autant de parametre que de clef pour accéder à la valeur ou un tableau 
     * avec toutes les clefs
     * Si aucune clef renvoie le tableau $_POST
     * @params int $tab - Le tableau visé (FORM_POST, FORM_GET, ...)
     * @params string $clef - La 1er clef pour accerder à la valeur
     * @return false|mixed
     */
    public function val($tab, $clef) {
        //Dans Request
        if ($tab == FORM_REQUEST) {
            //Si il y a plus que deux arguments
            if (func_num_args() > 2) {
                //Appel de la fonction avec lex arguments
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, 'request'), $args);
            }
            //Sinon appel de la fonction avec juste la clef
            return $this->request($clef);
        }
        //Dans Post
        else if ($tab == FORM_POST) {
            //Si il y a plus que deux arguments
            if (func_num_args() > 2) {
                //Appel de la fonction avec lex arguments
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, 'post'), $args);
            }
            //Sinon appel de la fonction avec juste la clef
            return $this->post($clef);
        }
        //Dans Get
        else if ($tab == FORM_GET) {
            //Si il y a plus que deux arguments
            if (func_num_args() > 2) {
                //Appel de la fonction avec lex arguments
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, 'get'), $args);
            }
            //Sinon appel de la fonction avec juste la clef
            return $this->get($clef);
        }
        //Si dans rien return false;
        else {
            return false;
        }
    }

    /**
     * Accède à une valeur de $_POST
     * Autant de parametre que de clef pour accéder à la valeur ou un tableau 
     * avec toutes les clefs
     * Si aucune clef renvoie le tableau $_POST
     * @return false|mixed
     */
    public function post() {
        //Si pas de parametre
        if (func_num_args() == 0) {
            return $_POST;
        }
        //Si un parametre
        else if (func_num_args() == 1) {
            //Si c'est un tableau de parametre on appel la fonction avec la bonne forme
            if (is_array(func_get_arg(0))) {
                return call_user_func_array(array($this, 'post'), func_get_arg(0));
            }
            //Si la clef existe
            else if (isset($_POST[func_get_arg(0)])) {
                //Si le resultat est un string et $protect on protege la valeur
                if (is_string($_POST[func_get_arg(0)]) && self::$protect) {
                    return htmlentities($_POST[func_get_arg(0)], ENT_QUOTES);
                } else {
                    return $_POST[func_get_arg(0)];
                }
            } else {
                return false;
            }
        }
        //Si + 1 parametre
        else {
            //On recup les parametres
            $args = func_get_args();
            $tab = $_POST;
            //On parcours le tableau pour trouver la valeur
            foreach ($args as $arg) {
                //Si le parametre existe et que c'est un tableau
                if (isset($tab[$arg]) && is_array($tab)) {
                    $tab = $tab[$arg];
                } else {
                    return false;
                }
            }
            //Protection si besoisn
            if (is_string($tab) && self::$protect) {
                $tab = htmlentities($tab, ENT_QUOTES);
            }
            return $tab;
        }
    }

    /**
     * Accède à une valeur de $_GET
     * Autant de parametre que de clef pour accéder à la valeur ou un tableau
     * avec toutes les clefs
     * Si aucune clef renvoie le tableau $_GET
     * @return false|mixed
     */
    public function get() {
        //Si pas de parametre
        if (func_num_args() == 0) {
            return $_GET;
        }
        //Si un parametre
        else if (func_num_args() == 1) {
            //Si c'est un tableau de parametre on appel la fonction avec la bonne forme
            if (is_array(func_get_arg(0))) {
                return call_user_func_array(array($this, 'get'), func_get_arg(0));
            }
            //Si la clef existe
            else if (isset($_GET[func_get_arg(0)])) {
                //Si le resultat est un string et $protect on protege la valeur
                if (is_string($_GET[func_get_arg(0)])) {
                    return htmlentities($_GET[func_get_arg(0)], ENT_QUOTES);
                } else {
                    return $_GET[func_get_arg(0)];
                }
            } else {
                return false;
            }
        }
        //Si + 1 parametre
        else {
            //On recup les parametres
            $args = func_get_args();
            $tab = $_GET;
            //On parcours le tableau pour trouver la valeur
            foreach ($args as $arg) {
                //Si le parametre existe et que c'est un tableau
                if (isset($tab[$arg]) && is_array($tab)) {
                    $tab = $tab[$arg];
                } else {
                    return false;
                }
            }
            //Protection si besoisn
            if (is_string($tab) && self::$protect) {
                $tab = htmlentities($tab, ENT_QUOTES);
            }
            return $tab;
        }
    }

    /**
     * Accède à une valeur de $_REQUEST et protge la valeur si possible
     * Autant de parametre que de clef pour accéder à la valeur ou un tableau
     * avec toutes les clefs
     * Si aucune clef renvoie le tableau $_REQUEST
     * @return false|mixed
     */
    public function request() {
        //Si pas de parametre
        if (func_num_args() == 0) {
            return $_REQUEST;
        }
        //Si un parametre
        else if (func_num_args() == 1) {
            //Si c'est un tableau de parametre on appel la fonction avec la bonne forme
            if (is_array(func_get_arg(0))) {
                return call_user_func_array(array($this, 'request'), func_get_arg(0));
            }
            //Si la clef existe
            else if (isset($_REQUEST[func_get_arg(0)])) {
                //Si le resultat est un string et $protect on protege la valeur
                if (is_string($_GET[func_get_arg(0)])) {
                    return htmlentities($_REQUEST[func_get_arg(0)], ENT_QUOTES);
                } else {
                    return $_REQUEST[func_get_arg(0)];
                }
            } else {
                return false;
            }
        }
        //Si + 1 parametre
        else {
            //On recup les parametres
            $args = func_get_args();
            $tab = $_REQUEST;
            //On parcours le tableau pour trouver la valeur
            foreach ($args as $arg) {
                //Si le parametre existe et que c'est un tableau
                if (isset($tab[$arg]) && is_array($tab)) {
                    $tab = $tab[$arg];
                } else {
                    return false;
                }
            }
            //Protection si besoisn
            if (is_string($tab) && self::$protect) {
                $tab = htmlentities($tab, ENT_QUOTES);
            }
            return $tab;
        }
    }

    /* ----- Récuperation et vérification des valeurs ----- */

    /**
     * Verifie si une valeur du tableau en parametre correspond à $check
     * @param int $tab - Le tableau (FORM_REQUEST, FORM_POST, ...)
     * @param int $check - Le type de verification (CHECK_STRING, CHECK_EMAIL, ...)
     * @params string $clef - La 1er clef pour accerder à la valeur. Autant 
     * de parametre que de clef pour accéder à la valeur ou un tableau 
     * avec toutes les clefs
     * @return mixed|false - La valeur ou false si elle ne correspond pas ou est introuvable
     */
    public function check($tab, $check, $clef) {
        //Dans Request
        if ($tab == FORM_REQUEST) {
            //Si il y a plus que deux arguments
            if (func_num_args() > 2) {
                //Appel de la fonction avec lex arguments
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, 'check_request'), $args);
            }
            //Sinon appel de la fonction avec juste la clef
            return $this->check_request($clef, $check);
        }
        //Dans Post
        else if ($tab == FORM_POST) {
            //Si il y a plus que deux arguments
            if (func_num_args() > 2) {
                //Appel de la fonction avec lex arguments
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, 'check_post'), $args);
            }
            //Sinon appel de la fonction avec juste la clef
            return $this->check_post($clef, $check);
        }
        //Dans Get
        else if ($tab == FORM_GET) {
            //Si il y a plus que deux arguments
            if (func_num_args() > 2) {
                //Appel de la fonction avec lex arguments
                $args = func_get_args();
                unset($args[0]);
                return call_user_func_array(array($this, 'check_get'), $args);
            }
            //Sinon appel de la fonction avec juste la clef
            return $this->check_get($clef, $check);
        }
        //Si dans rien return false;
        else {
            return false;
        }
    }

    /**
     * Verifie si une valeur de POST correspond à $check
     * @param int $check - Le type de verification (CHECK_STRING, CHECK_EMAIL, ...)
     * @params string $clef - La 1er clef pour accerder à la valeur. Autant 
     * de parametre que de clef pour accéder à la valeur ou un tableau 
     * avec toutes les clefs
     * @return mixed|false - La valeur ou false si elle ne correspond pas ou est introuvable
     */
    public function check_post($check, $clef) {
        //Recup de la valeur
        $val = false;
        if (func_num_args() > 2) {
            //Appel de la fonction avec lex arguments
            $args = func_get_args();
            unset($args[0]);
            $val = call_user_func_array(array($this, 'post'), $args);
        } else {
            $val = $this->post($clef);
        }
        //Si pas de valeur
        if ($val === false) {
            return false;
        }
        //Verification
        return $this->check_value($val, $check);
    }

    /**
     * Verifie si une valeur de GET correspond à $check
     * @param int $check - Le type de verification (CHECK_STRING, CHECK_EMAIL, ...)
     * @params string $clef - La 1er clef pour accerder à la valeur. Autant 
     * de parametre que de clef pour accéder à la valeur ou un tableau 
     * avec toutes les clefs
     * @return mixed|false - La valeur ou false si elle ne correspond pas ou est introuvable
     */
    public function check_get($check, $clef) {
        //Recup de la valeur
        $val = false;
        if (func_num_args() > 2) {
            //Appel de la fonction avec lex arguments
            $args = func_get_args();
            unset($args[0]);
            $val = call_user_func_array(array($this, 'get'), $args);
        } else {
            $val = $this->get($clef);
        }
        //Si pas de valeur
        if ($val === false) {
            return false;
        }
        //Verification
        return $this->check_value($val, $check);
    }

    /**
     * Verifie si une valeur de GET correspond à $check
     * @param int $check - Le type de verification (CHECK_STRING, CHECK_EMAIL, ...)
     * @params string $clef - La 1er clef pour accerder à la valeur. Autant 
     * de parametre que de clef pour accéder à la valeur ou un tableau 
     * avec toutes les clefs
     * @return mixed|false - La valeur ou false si elle ne correspond pas ou est introuvable
     */
    public function check_request($check, $clef) {
        //Recup de la valeur
        $val = false;
        if (func_num_args() > 2) {
            //Appel de la fonction avec lex arguments
            $args = func_get_args();
            unset($args[0]);
            $val = call_user_func_array(array($this, 'request'), $args);
        } else {
            $val = $this->request($clef);
        }
        //Si pas de valeur
        if ($val === false) {
            return false;
        }
        //Verification
        return $this->check_value($val, $check);
    }

    /**
     * Test un ensemble de champs
     * @param int $tab - Le tableau (FORM_REQUEST, FORM_POST, ...)
     * @param mixed $data - Le champs à tester et les test à effectuer
     * Sous la forme array("champs" => array(CHECK_, CHECK_, ...), ...)
     * @return boolean
     */ 
    public function check_form($tab, $data){
        //On recupere le bon tableau
        switch ($tab) {
            case FORM_POST:
                $tab = $_POST;
                break;
            case FORM_GET:
                $tab = $_GET;
                break;
            case FORM_REQUEST:
                $tab = $_REQUEST;
                break;
            default:
                return false;
        }
        //On parcours les champs à verifier
        foreach ($data as $champ => $verif) {
            //On recupere la valeur dans le tableau
            if(!isset($tab[$champ])){
                //Si elle n'existe pas on regarde si elle etait required
                if(in_array(CHECK_REQUIRED, $verif)){
                    return false;
                }
            } else {
                $val = $tab[$champ];
                //Si il n'y a q'une contrainte on la met en tableau
                if(!is_array($verif)){
                    $verif = array($verif);
                }
                //On teste toutes les contraintes
                foreach ($verif as $check) {
                    //Si un seul retour faux on arrete
                    if($this->check_value($val, $check) === false){
                        return false;
                    }
                }
            }
        }
        //Si on arrive au bout tous est ok
        return true;
    }

    /**
     * Verifie une valeur par rapport à une constante CHECK
     * @param type $val - LA valeur
     * @param type $check - La constante
     * @return mixed|false - La valeur ou false si elle ne correspond pas
     */
    private function check_value($val, $check) {
        //Verification selon le type
        switch ($check) {
            case CHECK_STRING:
                if (is_string($val) && trim($val) != '') {
                    return $val;
                }
                return false;
            case CHECK_INT:
                if (ctype_digit(strval($val))) {
                    return (int) $val;
                }
                return false;
            case CHECK_FLOAT:
                if (is_float($val + 0)) {
                    return (float) $val;
                }
                return false;
            case CHECK_EMAIL:
                if ((bool) filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    return $val;
                }
                return false;
            case CHECK_URL:
                if ((bool) filter_var($val, FILTER_VALIDATE_URL)) {
                    return $val;
                }
                return false;
            case CHECK_DATE:
                if ($this->check_date($val)) {
                    return $val;
                }
                return false;
            case CHECK_PHONE:
                if ((preg_match('#^(0[1-589])(?:[ /_.-]?(\d{2})){4}$#', $val) || preg_match('#^0[6-7]([-._/ ]?[0-9]{2}){4}$#', $val))) {
                    return $val;
                }
                return false;
            case CHECK_ARRAY:
                if (is_array($val) && !empty($val)) {
                    return $val;
                }
                return false;
            case CHECK_REQUIRED:
                if(trim($val) != ''){
                    return true;
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * Permet de vérifier que le paramètre passé est une date FR ou US 
     * @param string $date - Valeur à vérifier
     * @return boolean
     */
    private function check_date($date) {
        //Découpe la date
        if (strpos($date, '/') !== false) {
            $date = explode('/', $date);
        } else if (strpos($date, '.') !== false) {
            $date = explode('.', $date);
        } else if (strpos($date, '-') !== false) {
            $date = explode('-', $date);
        } else {
            return false;
        }
        //Verifie les differents elements
        if(count($date) != 3){
            return false;
        }
        if(!(ctype_digit(strval($date[0])) && ctype_digit(strval($date[1])) && ctype_digit(strval($date[2])))){
            return false;
        }
        //Si c'est une date europeen
        if (strlen($date[0]) == 2 && strlen($date[1]) == 2 && strlen($date[2]) == 4) {
            return checkdate($date[1], $date[0], $date[2]);
        }
        //Si c'est une date us
        else if (strlen($date[0]) == 4 && strlen($date[1]) == 2 && strlen($date[2]) == 2) {
            return checkdate($date[1], $date[2], $date[1]);
        }
        //Sinon false
        return false;
    }

    /* ----- Upload fichier (formulaire html) ----- */

    /**
     * Change la fonction de verification du mimetype lors d'un upload
     * Utilise l'extension
     */
    public function mime_by_ext() {
        self::$mime_file = false;
    }

    /**
     * Change la fonction de verification du mimetype lors d'un upload
     * Utilise les données du fichier
     */
    public function mime_by_file() {
        self::$mime_file = true;
    }

    /**
     * Upload un fichier depuis un formulaire HTML
     * @param string $name - Le nom du champ du formulaire HTML
     * @param string $destination - Le chemin pour stocker le fichier
     * @param string $final_name - [optional] Le nom du fichier (par defaut le même que sur le pc de l'utilisateur)
     * @param array $ext - [optional] Liste des extensions autorisées
     * @param int $size - [optional] Taille maximum du fichier autorisé
     * @return boolean
     */
    public function upload($name, $destination, $final_name = '', $ext = null, $size = 0) {
        //Recuperation instance de Fraquicom
        $fc = get_instance();
        $fc->load->helper('file');
        //Si le fichier est correctement uploadé
        if (!isset($_FILES[$name]) OR $_FILES[$name]['error'] > 0) {
            switch ($_FILES[$name]['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $this->upload_err = 'Fichier trop volumineux par rapport à upload_max_filesize de ini.php';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->upload_err = 'Fichier trop volumineux par rapport à l\'indication du formulaire html';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->upload_err = 'Fichier uploader partiellement';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->upload_err = 'Aucun fichier uploadé';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->upload_err = 'Aucun fichier temporaire pour sauvegarder le fichier';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->upload_err = 'Impossible d\'écrire sur le disque';
                    break;
                default:
                    $this->upload_err = 'Erreur lors de l\'upload du fichier';
            }
            return false;
        }
        //Si on doit vérifier l'extension
        if ($ext !== null && is_array($ext) && !empty($ext)) {
            //Recupération de l'extension
            $mime = get_mime_by_extension($_FILES[$name]['tmp_name']);
            if (self::$mime_file) {
                $mime = get_mime_by_file($_FILES[$name]['tmp_name']);
            }
            $extension = get_extension($mime);
            //Verifie que l'extension est valide
            if (!in_array($extension, $ext)) {
                $this->upload_err = 'Extension invalide';
                return false;
            }
        }
        //Verif de la taille
        if ($size > 0 && $_FILES[$name]['size'] > $size) {
            $this->upload_err = 'Fichier trop volumineux';
            return false;
        }
        //Si on doit renommer ou non le fichier
        if (trim($final_name) == '') {
            var_dump($_FILES[$name]['name']);
            //Recuperation du nom d'origine
            $explode = explode('/', str_replace('\\', '/', $_FILES[$name]['name']));
            $final_name = $explode[count($explode) - 1];
        }
        //Déplacement
        make_dir($destination);
        return move_uploaded_file($_FILES[$name]['tmp_name'], str_replace('//', '/', $destination . '/' . $final_name));
    }

    /**
     * Retourne le libelle de l'erreur lors de l'upload
     * @return string
     */
    public function upload_error() {
        return $this->upload_err;
    }

}