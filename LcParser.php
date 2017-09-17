<?php
/* =============================================================================
 * LcParser by Loquicom
 * Ver 1.1
 * =========================================================================== */

/*
 * Constante pour la cr?ation de l'objet 
 */
define('INI', 0);
define('CSV', 1);
define('JSON', 2);
define('XML', 3);

/**
 * 
 */
class LcParser {

    /**
     * Parametre propre aux diff?rents type de parse
     * @var mixed
     */
    protected $params = array(
        'ini_comment' => '#',
        'csv_delimiter' => ';',
        'csv_replace_key' => null,
        'xml_main_container' => ''
    );

    /**
     * Le type de fichier a parser
     * @var string
     */
    protected $type = '';

    /**
     * Les types de fichier accept?
     * @var string[]
     */
    protected $acceptedType = array(
        'ini',
        'csv',
        'json',
        'xml'
    );

    /**
     * Le tableau de donn?es
     * @var mixed 
     */
    protected $data = array();

    /**
     * Le nom du fochier
     * @var string
     */
    protected $fileName = '';

    /**
     * Le contenue du fichier (Le string ? parser)
     * @var string
     */
    protected $fileContent = '';

    /**
     * 
     * @param type $type
     * @param type $data
     * @throws LcScriptError
     */
    public function __construct($type, $data = null) {
        //Transforme toutes les erreurs en exception
        set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
            if (0 === error_reporting()) {
                return false;
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        //Si le type est un string on regarde si il est valide
        if (in_array(strtolower($type), $this->acceptedType)) {
            $this->type = strtolower($type);
        }
        //Sinon si c'est on regarde si c'est une clef du tableau
        else if (isset($this->acceptedType[$type])) {
            $this->type = $this->acceptedType[$type];
        }
        //Sinon erreur
        else {
            throw new LcScriptError('Type invalide');
        }

        //Si il y a des donn�es on regarde si c'est un fichier a parser ou un tableau a transformer
        if (is_array($data)) {
            //Sauvegarde des donn?es
            $this->data = $data;
            //On tente de transformer le tableau dans le type demander
            return $this->transform();
        } else if (is_string($data) && file_exists($data)) {
            //Sauvegarde des donn?es
            $this->fileName = $data;
            $this->fileContent = file_get_contents($data);
            //On tente de parser le fichier
            return $this->parse();
        } else if (is_string($data)) {
            //Sauvegarde des donn?es
            $this->fileContent = $data;
            //On tente de parser le string
            return $this->parse();
        }
    }

    public function setParams($params, $val = '') {
        //Si params est un tableau on remplace les champs
        if (is_array($params) && !empty($params)) {
            if (isset($params['ini_comment'])) {
                $this->params['ini_comment'] = $params['ini_comment'];
            }
            if (isset($params['csv_delimiter'])) {
                $this->params['csv_delimiter'] = $params['csv_delimiter'];
            }
            if (isset($params['csv_replace_key'])) {
                $this->params['csv_replace_key'] = $params['csv_replace_key'];
            }
            if (isset($params['xml_main_container'])) {
                $this->params['xml_main_container'] = $params['xml_main_container'];
            }
        }
        //Si params est un string on replace le champ design? par val
        if (in_array($params, array('ini_comment', 'csv_delimiter', 'csv_replace_key', 'xml_main_container'))) {
            $this->params[$params] = $val;
        }
    }

    public function getData() {
        return $this->data;
    }

    public function getFileName() {
        return $this->fileName;
    }

    public function getFileContent() {
        return $this->fileContent;
    }

    public function parse($content = '') {
        //Si il y a un parametre on l'utilise
        if (trim($content) != '' && file_exists($content)) {
            $this->fileName = $content;
            $this->fileContent = file_get_contents($content);
        } else if (trim($content) != '' && is_string($content)) {
            $this->fileContent = $content;
        }
        //Sinon on verifie qu'il y a deja un fichier de set
        else if (trim($this->fileContent) == '') {
            //Si ce n'est pas le cas erreur
            throw new LcScriptError('Aucun fichier à parser');
        }

        //On appel la bonne fonction
        try {
            $result = false;
            switch ($this->type) {
                case 'ini' :
                    $result = $this->ini_to_array();
                    break;
                case 'csv' :
                    $result = $this->csv_to_array();
                    break;
                case 'json' :
                    $result = $this->json_to_array();
                    break;
                case 'xml' :
                    $result = $this->xml_to_array();
                    break;
                default :
                    throw new LcScriptError('Type invalide');
            }
            //En cas d'erreur (result false)
            if ($result === false) {
                throw new LcScriptError('Impossible de parser');
            }
        } catch (Exception $e) {
            throw new LcScriptError('Impossible de parser : ' . $e->getMessage());
        }

        //Retour
        return $this->data;
    }

    public function transform($data = array()) {
        //Si il y a un parametre on l'utilise
        if (is_array($data) && !empty($data)) {
            $this->data = $data;
        }
        //Sinon on verifie qu'il y a deja des donn?es
        else if (empty($this->data)) {
            //Si ce n'est pas le cas erreur
            throw new LcScriptError('Aucune données à transformer');
        }

        //On appel la bonne fonction
        try {
            $result = false;
            switch ($this->type) {
                case 'ini' :
                    $result = $this->array_to_ini();
                    break;
                case 'csv' :
                    $result = $this->array_to_csv();
                    break;
                case 'json' :
                    $result = $this->array_to_json();
                    break;
                case 'xml' :
                    $result = $this->array_to_xml();
                    break;
                default :
                    throw new LcScriptError('Type invalide');
            }
            //En cas d'erreur (result false)
            if ($result === false) {
                throw new LcScriptError('Impossible de transformer');
            }
        } catch (Exception $e) {
            throw new LcScriptError('Impossible de transformer : ' . $e->getMessage());
        }

        //Retour
        return $this->fileContent;
    }

    public function write($fileName) {
        //On verifie qu'il y a un contenue a ecrire
        if (trim($this->fileContent) == '') {
            return false;
        }
        //Si il n' y a pas l'extension on l'ajoute
        if (strpos($fileName, $this->type) === false) {
            $fileName = $fileName . '.' . $this->type;
        }
        //Ouverture du fichier
        $file = @fopen($fileName, 'w');
        if ($file === false) {
            throw new LcScriptError('Impossible d\'écrire dans le fichier');
        }
        //Ecriture
        fputs($file, $this->fileContent);
        fclose($file);
        return true;
    }

    public function reset() {
        $this->data = array();
        $this->fileName = '';
        $this->fileContent = '';
    }

    public function __toString() {
        return $this->fileContent;
    }

    /* ===== Fonction prot?g?e de transformation pour chaque type ===== */

    protected function ini_to_array() {
        //Cr?ation d'un fichier temporaire
        if (!$this->create_file('./lcparser_tmp.ini', $this->fileContent)) {
            return false;
        }
        $ini = file('./lcparser_tmp.ini');
        //Parcours le fichier ini
        $matches = array();
        $fichier_ini = null;
        foreach ($ini as $ligne) {
            if (trim($ligne) != '') {
                $ligne_propre = trim($ligne);
                if (preg_match("#^\[(.+)\]$#", $ligne_propre, $matches)) {
                    $groupe_curseur = utf8_encode($matches[1]);
                } else {
                    if ($ligne_propre[0] != $this->params['ini_comment'] && $tableau = explode("=", $ligne, 2)) {
                        $tableau = array_map('trim', array_map('utf8_encode', $tableau));
                        $fichier_ini[$groupe_curseur][$tableau[0]] = rtrim($tableau[1], "\n\r");
                    }
                }
            }
        }
        $this->data = $fichier_ini;
        //On supprime et on revoie vrai
        @unlink('./lcparser_tmp.ini');
        return true;
    }

    protected function array_to_ini() {
        $this->fileContent = utf8_encode($this->fileContent);
        $ini = "";
        foreach ($this->data as $key => $groupe) {
            $ini .= "[" . $key . "]" . "\r\n";
            foreach ($groupe as $key => $item) {
                $ini .= "  " . $key . "=" . $item . "\r\n";
            }
        }
        $this->fileContent = rtrim($ini, '\r\n');
        return true;
    }

    protected function csv_to_array() {
        //Si $tabKey n'est pas un tableau on le transforme en tableau 
        if (!is_array($this->params['csv_replace_key']) && $this->params['csv_replace_key'] !== null) {
            $tabKey = array($tabKey);
        } else {
            $tabKey = $this->params['csv_replace_key'];
        }
        //Les clef du tableau et le tableau de donn?es
        $keys = null;
        $data = array();
        //Cr?ation d'un fichier temporaire
        if (!$this->create_file('./lcparser_tmp.csv', $this->fileContent)) {
            return false;
        }
        //Ouverture du fichier tmp et parcours
        if ($csv = fopen('./lcparser_tmp.csv', 'r')) {
            //On parcours le fichier
            while ($line = fgetcsv($csv, 0, $this->params['csv_delimiter'])) {
                //Encodage utf8
                $line = array_map('utf8_encode', $line);
                //Si les clef ne sont pas encore definie
                if ($keys === null) {
                    //On les assigne
                    if ($tabKey == null) {
                        $keys = $line;
                    } else if (count($tabKey) == count($line)) {
                        $keys = $tabKey;
                    } else {
                        $tab = array();
                        for ($i = 0; $i < count($line); $i++) {
                            if (isset($tabKey[$i])) {
                                $tab[] = $tabKey[$i];
                            } else {
                                $tab[] = $line[$i];
                            }
                        }
                        $keys = $tab;
                    }
                }
                //Sinon on ajoute les valeurs dans data
                else {
                    try {
                        $data[] = array_combine($keys, $line);
                    } catch (Exception $e) {
                        return false;
                    }
                }
            }
            fclose($csv);
            $this->data = $data;
            //On supprime et on revoie vrai
            @unlink('./lcparser_tmp.csv');
            return true;
        } else {
            return false;
        }
    }

    protected function array_to_csv() {
        //Attribution des variables
        $array = $this->data;
        //On decode toutes les valeur du tableau
        for ($i = 0; $i < count($array); $i++) {
            $array[$i] = array_map('utf8_decode', $array[$i]);
        }
        $csv = @fopen('./lcparser_tmp.csv', 'w');
        if ($csv !== false) {
            $first = true; //Repere du premier tour pour definir les noms des colonnes
            foreach ($array as $subArray) {
                //Si c'est le 1er tour on d?finit le nom des colonnes
                if ($first) {
                    $clef = array(); //Tableau contenant les clef des colonnes
                    //Si on ne donne aucune clef on prend celle par default
                    if (!is_array($this->params['csv_replace_key'])) {
                        foreach ($subArray as $key => $val) {
                            $clef[] = $key;
                        }
                    }
                    //Sinon si il fait pile la bonne taille
                    else if (count($this->params['csv_replace_key']) == count($subArray)) {
                        $clef = $this->params['csv_replace_key'];
                    }
                    //Sinon si il a une taille diff?rente
                    else {
                        $subArrayKey = array_keys($subArray); //Les clef du sous tableau
                        for ($i = 0; $i < count($subArray); $i++) {
                            //Si la vlauer existe dans colname on la prend, sinon on prend celle par defaut 
                            if (isset($this->params['csv_replace_key'][$i])) {
                                $clef[] = $this->params['csv_replace_key'][$i];
                            } else {
                                $clef[] = $subArrayKey[$i];
                            }
                        }
                    }
                    //On met le contenue de clef dans le fichier
                    if (fputcsv($csv, $clef, $this->params['csv_delimiter']) === false) {
                        return false;
                    }
                    $first = false;
                }
                //On met les valeurs de la ligne dans le fichier csv
                if (fputcsv($csv, $subArray, $this->params['csv_delimiter']) === false) {
                    return false;
                }
            }
            fclose($csv);
            //On recup?re le contenue du fichier
            $this->fileContent = file_get_contents('./lcparser_tmp.csv');
            //On supprime et on revoie vrai
            @unlink('./lcparser_tmp.csv');
            return true;
        } else {
            return false;
        }
    }

    protected function json_to_array() {
        $this->data = json_decode($this->fileContent);
        return true;
    }

    protected function array_to_json() {
        $this->fileContent = json_encode($this->data);
        return true;
    }

    protected function xml_to_array() {
        //Transformation du xml pour le mettre sur une ligne
        $xmlLine = str_replace(" ", "", str_replace("\n", "", str_replace("\r\n", "", $this->fileContent)));
        //O9n parcoours la chaine obtenue et on parse
        $data = array();
        while (strlen($xmlLine) > 1) {
            //Si c'est un commentaire ou une fin de ligne on retire la balise du tableau
            if (in_array(substr($xmlLine, 0, 2), array('</', '<!'))) {
                $xmlLine = substr($xmlLine, strpos($xmlLine, '>') + 1);
            }
            //Sinon on ajoute une clef ou une valeur dans le tableau
            else {
                //Clef
                if ($xmlLine[0] == '<') {
                    //On recupere la clef
                    $key = substr($xmlLine, 1, strpos($xmlLine, '>') - 1);
                    //On raccourcis la chaine
                    $xmlLine = substr($xmlLine, strpos($xmlLine, '>') + 1);
                    //On parse
                    $return = $this->parse_xml($xmlLine, $key);
                    $data[$key] = $return['data'];
                    $xmlLine = $return['xmlLine'];
                }
            }
        }
        $this->data = $data;
        return true;
    }

    private function parse_xml($xmlLine, $key) {
        //On initialise le tableau de données avec la clef en parametre
        $data = array();
        //On parcours le xml jusqu'a una balise fermente
        $i = 0; //Pour la position des sous balises
        while (substr($xmlLine, 0, 2 + strlen($key)) != '</' . $key) {
            //Si c'est un commentaire ou une fin de ligne on retire la balise du tableau
            if (in_array(substr($xmlLine, 0, 2), array('</', '<!'))) {
                $xmlLine = substr($xmlLine, strpos($xmlLine, '>') + 1);
            }
            //Sinon on ajoute une clef ou une valeur dans le tableau
            else {
                //Clef
                if ($xmlLine[0] == '<') {
                    //On recupere la clef
                    $newKey = substr($xmlLine, 1, strpos($xmlLine, '>') - 1);
                    //On raccourcis la chaine
                    $xmlLine = substr($xmlLine, strpos($xmlLine, '>') + 1);
                    //On parse
                    $return = $this->parse_xml($xmlLine, $newKey);
                    //v($return['data']);
                    if ($newKey === array_keys($return['data'])[0]) {
                        $data[] = $return['data'];
                    } else {
                        foreach ($return['data'] as $tab) {
                            foreach ($tab as $keyTab => $val) {
                                $j = 0; //Si il y a deja un champ set on decale
                                if (isset($data[$i]) && is_array($data[$i][$newKey][$j])) {
                                    while (isset($data[$i][$newKey][$j][$keyTab])) {
                                        $j++;
                                    }
                                    $data[$i][$newKey][$j][$keyTab] = $val;
                                } else {
                                    $data[][$newKey][$j][$keyTab] = $val;
                                }
                            }
                        }
                        $i++;
                    }
                    $xmlLine = $return['xmlLine'];
                }
                //Valeur
                else {
                    $data[$key] = substr($xmlLine, 0, strpos($xmlLine, '<'));
                    //On raccourcis la chaine
                    $xmlLine = substr($xmlLine, strpos($xmlLine, '<'));
                }
            }
        }
        return array('data' => $data, 'xmlLine' => $xmlLine);
    }

    protected function array_to_xml() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
        //Si il y a une balise global par defaut
        if (trim($this->params['xml_main_container']) != '') {
            $xml .= '<' . $this->params['xml_main_container'] . '>' . "\r\n";
        }
        foreach ($this->data as $key => $val) {
            if (is_array($val)) {
                $xml .= '    <' . $key . '>  ' . "\r\n" . $this->transform_xml($val) . '</' . $key . '>' . "\r\n";
            } else {
                $xml .= '    <' . $key . '>' . $val . '</' . $key . '>' . "\r\n";
            }
        }
        //Si il y a une balise global par defaut
        if (trim($this->params['xml_main_container']) != '') {
            $xml .= '</' . $this->params['xml_main_container'] . '>';
        } else {
            //On retire les espaces en trop
            $xml = str_replace('    <', '<', $xml);
        }
        $this->fileContent = $xml;
        return true;
    }

    private function transform_xml($array, $space = '') {
        $xml = '    ' . $space;
        $newSpace = $xml;
        foreach ($array as $tab) {
            foreach ($tab as $key => $val) {
                if (is_array($val)) {
                    $xml .= '    <' . $key . '>  ' . "\r\n" . $this->transform_xml($val, $newSpace) . '</' . $key . '>' . "\r\n" . '    ' . $space;
                } else {
                    $xml .= '    <' . $key . '>' . $val . '</' . $key . '>' . "\r\n" . '    ' . $space;
                }
            }
        }
        return $xml;
    }

    /* ===== Fonction utilitaire ===== */

    protected function create_file($path, $content = '') {
        $file = fopen($path, 'w');
        if ($file === false) {
            return false;
        }
        fclose($file);
        file_put_contents($path, $content);
        return true;
    }

}

class LcScriptError extends Exception {
    
}
