<?php

/* =============================================================================
 * LcXmlParser by Loquicom
 * Ver 1.0
 * =========================================================================== */

define("XML_PARSER_OBJECT", 1);
define("XML_PARSER_ARRAY", 2);

/**
 * Surcharge de SimpleXml pour acceder plus facilement aux elements
 */
class LcXmlParser {

    /**
     * Indique comme sont retournée les données
     * 1 - Objet, 2 - Tableau (dans l'attribut data)
     * @var int 
     */
    protected static $load_type = 1;

    /* ===== Méthode Statique ===== */
    
    /**
     * Retourne le type de chargement des données
     * @return int
     */
    public static function get_load_type(){
        return static::$load_type;
    }

    /**
     * Change le type de chargement des données
     * @param int $type
     * @return boolean
     */
    public static function set_load_type($type){
        if(!in_array($type, [1, 2])){
            return false;
        }
        static::$load_type = $type;
        return true;
    }
    
    /**
     * Charge un document XML
     * @param string $xml - le xml ou le chemin vers le fichier xml
     * @return boolean|\self
     */
    public static function load($xml) {
        if (trim($xml) == '') {
            return false;
        }
        //Charge le XML
        if (file_exists($xml)) {
            $xml = file_get_contents($xml);
        }
        $sxml = new SimpleXMLElement($xml);
        //Création de l'objet
        $obj = new self();
        //Transforme les données en tableau exploitable
        $data = [];
        $obj->convert($sxml, $data);
        //Adapte le type de données dans l'objet
        if(static::$load_type == 1){
            //Ajoute les element en tant qu'objet
            return $obj->to_object($data);
        } else if(static::$load_type == 2) {
            //Ajoute le tableau de données dans l'objet
            $obj->data = $data;
            return $obj;
        }
        return false;
    }
    
    /* ===== Méthode publique ===== */
    
    /**
     * Retourne les données
     * @return mixed
     */
    public function get_data(){
        if(isset($this->data)){
            return $this->data;
        } else {
            return $this;
        }
    }
    
    /**
     * Méthode magique pour accèder aux différentes données du XML
     * @param string $name - Clef
     * @return boolean|\self
     */
    public function __get($name) {
        if(isset($this->data)){
            //Verif que la valeur existe
            if(!isset($this->data[$name])){
                return false;
            }
            //Retourne un objet avec la valeur cherché
            $obj = new self();
            $obj->data = $this->data[$name];
            return $obj;
        } else {
            //Verif que la valeur existe
            if(!isset($this->$name)){
                return false;
            }
            //Retourne un objet avec la valeur cherché
            return $this->$name;
        }
    }
    
    /* ===== Méthode protégée & utilitaire ===== */

    /**
     * Constructeur pour generer un objet vide
     */
    protected function __construct() {
        //Juste à generer un objet vide
    }

    /**
     * Convertit un objet SimpleXml en tableau de données
     * @param SimpleXml $sxml
     * @param mixed $res - Le tableau de resultat (passage par reference)
     * @param mixed $keyList
     */
    protected function convert($sxml, &$res, $keyList = []) {
        //Transforme le 1er niveau du SimpleXml en tableau
        $tabSxml = (array) $sxml;
        //Parcours chaque valeur du SimpleXml
        foreach ($tabSxml as $key => $val) {
            //Si c'est un objet on rapelle cette méthode
            if (is_object($val)) {
                $this->convert($val, $res, array_merge($keyList, [$key]));
            }
            //Sinon on parcours jusqu'a la valeur pour l'ajouter dans le tableau de retour
            else {
                $tab = & $res;
                foreach ($keyList as $clef) {
                    if (!isset($tab[$clef])) {
                        $tab[$clef] = [];
                    }
                    $tab = & $tab[$clef];
                }
                $tab[$key] = $val;
            }
        }
    }

    /**
     * Transforme un tableau en objet
     * @param mixed $tab
     * @return object
     */
    protected function to_object($tab) {
        $keys = array_keys($tab);
        for ($i = 0; $i < count($tab); $i++) {
            $key = $keys[$i];
            $val = $tab[$key];
            if (is_array($val)) {
                $tab[$key] = $this->to_object($val);
            }
            if (ctype_digit(strval($key))) {
                unset($tab[$key]);
                $tab['clef_' . $key] = $val;
            }
        }
        return $this->object_cast($tab);
    }

    /**
     * Cast des données en LcXmlReader
     * @param mixed $data - Les données
     * @return \self
     */
    protected function object_cast($data) {
        $obj = new self();
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $obj->$key = $this->object_cast($val);
            } else {
                $obj->$key = $val;
            }
        }
        return $obj;
    }

}
