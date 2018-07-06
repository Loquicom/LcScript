<?php

/* =============================================================================
 * LcFeistel by Loquicom
 * Ver 1.2
 * =========================================================================== */

define("LCFEISTEL_NONE", 0);
define("LCFEISTEL_ADD", 1);
define("LCFEISTEL_REPLACE", 2);

class LcFeistel {

    /**
     * Taiile des bloc de bit manipulé par les noeuds (defaut 2)
     * @var int
     */
    protected static $bit_block_length = 2;

    /**
     * Clef de chiffrement
     * @var int
     */
    protected $key;

    /**
     * Liste des fonctions utilisateurs
     * @var function
     */
    protected $function = [];

    /**
     * Liste des fonctions à utiliser
     * @var string[]
     */
    protected $noeud = ["f1", "f2", "f3", "f4"];

    /**
     * Le resultat de la dernière opération effectuée
     * @var int|null
     */
    protected $last_operation = null;

    /**
     * Instancie la class
     * @param int $key - La clef 0 pour ne pas en utiliser
     * @throws LcFeistel_Exception
     */
    public function __construct($key = 0) {
        $this->set_key($key);
    }

    /* === Les méthodes de (de)chiffrement === */

    /**
     * Appel automatiquement la bonne méthode (chiffrement/dechiffrement)
     * @param int|float $data
     * @return int|float
     */
    public function feistel($data){
        //Si c'est un entier chiffrement
        if($this->is_integer($data)){
            return $this->crypt($data);
        }
        //Sinon déchiffre
        else {
            if($this->is_integer(-1 * $data)){
                throw new LcFeistel_Exception('$data n\'est pas un entier positif');  
            }
            return $this->decrypt($data);
        }
    }

    /**
     * Chiffre un entier
     * @param int $data - L'entier
     * @return float - L'entier chiffré
     * @throws LcFeistel_Exception
     */
    public function crypt($data) {
        //Verifie que le tableau noeud n'est pas vide
        if (empty($this->noeud)) {
            throw new LcFeistel_Exception("Aucune fonction à appliquer");
        }
        //Verifie que date est un entier
        if(!$this->is_integer($data)){
            throw new LcFeistel_Exception('$data n\'est pas un entier positif');
        }
        if($data < 0){
            throw new LcFeistel_Exception('$data n\'est pas un entier positif');
        }
        //Si une clef on l'ajoute aux données
        if ($this->key != 0) {
            $data = $this->add_key($data);
        }
        //Préparation de la données
        $bin = $this->cut_bin($this->complete_bin(decbin($data)));
        //Chiffrement
        $res = [];
        for ($i = 0; $i < count($bin); $i += 2) {
            $g = [];
            $d = [];
            $g[0] = $bin[$i];
            $d[0] = $bin[$i + 1];
            $j = 1;
            foreach ($this->noeud as $function) {
                $g[$j] = $d[$j - 1];
                //Appel la bonne fonction/méthode
                if (method_exists($this, $function)) {
                    $d[$j] = $this->bin_xor($g[$j - 1], $this->$function($d[$j - 1]));
                } else if (isset($this->function[$function])) {
                    $d[$j] = $this->bin_xor($g[$j - 1], $this->function[$function]($d[$j - 1]));
                } else {
                    throw new LcFeistel_Exception("La fonction/méthode demandée ($function) n'existe pas");
                }
                $j++;
            }
            //Ajout du resultat
            $res[] = $g[$j - 1] . $d[$j - 1];
        }
        //Mise en forme du resultat
        $crypt = implode('', $res);
        $this->last_operation = (float) ((int) bindec($crypt) . '.' . strlen($crypt) . mt_rand(1, 9));
        return $this->last_operation;
    }

    /**
     * Déchiffre un entier chiffré par cette class
     * @param float $data - Données à dchifrer
     * @return int - L'entier d'originr
     * @throws LcFeistel_Exception
     */
    public function decrypt($data) {
        //Verifie que le tableau noeud n'est pas vide
        if (empty($this->noeud)) {
            throw new LcFeistel_Exception("Aucune fonction à appliquer");
        }
        //Découpe du nombre
        $data = explode('.', '' . $data);
        if (count($data) != 2) {
            throw new LcFeistel_Exception("Erreur");
        }
        //Préparation de la données
        $bin = $this->cut_bin($this->complete_bin(decbin($data[0]), (int) substr($data[1], 0, strlen($data[1]) - 1)));
        //Prend les noeuds dans l'odre inverse
        $noeud = array_reverse($this->noeud);
        //Dechiffre
        $res = [];
        for ($i = count($bin) - 1; $i > 0; $i -= 2) {
            $g = [];
            $d = [];
            $g[0] = $bin[$i];
            $d[0] = $bin[$i - 1];
            $j = 1;
            foreach ($noeud as $function) {
                $g[$j] = $d[$j - 1];
                //Appel la bonne fonction/méthode
                if (method_exists($this, $function)) {
                    $d[$j] = $this->bin_xor($g[$j - 1], $this->$function($d[$j - 1]));
                } else if (isset($this->function[$function])) {
                    $d[$j] = $this->bin_xor($g[$j - 1], $this->function[$function]($d[$j - 1]));
                } else {
                    throw new LcFeistel_Exception("La fonction/méthode demandée ($function) n'existe pas");
                }
                $j++;
            }
            //Ajout du resultat
            $res[] = $d[$j - 1] . $g[$j - 1];
        }
        //Concat et convert
        $uncrypt = bindec(implode('', array_reverse($res)));
        //Si il y a une clef on la retire
        if ($this->key != 0) {
            $uncrypt = $this->remove_key($uncrypt);
        }
        //Sauvegarde et retour
        $this->last_operation = (int) $uncrypt;
        return $this->last_operation;
    }

    /* === Getter / Setter === */

    /**
     * Récupère la nombre de bit par bloc utilisé pour le (de)chiffrement
     * @return int
     */
    public static function get_bit_block_length(){
        return self::$bit_block_length;
    }

    /**
     * Modifie le nombre de bit par bloc utilisé pour le (de)chiffrement
     * @param int $length - Le nombre de bit par bloc (entier > 0)
     */
    public static function set_bit_block_length($length){
        if(!ctype_digit(strval($nb))){
            throw new LcFeistel_Exception('$length n\'est pas un entier');
        }
        if($length < 1){
            throw new LcFeistel_Exception('$length doit être supérieur à 0');
        }
        self::$bit_block_length = $length;
    }

    /**
     * Récupère la liste des fonctions/méthodes utilisées dans le chiffrement
     * @return string[]
     */
    public function get_noeud() {
        return $this->noeud;
    }

    /**
     * Modifie la liste des fonctions/méthodes utilisées dans le chiffrement
     * @param string|string[] $noeud
     * @param int $action - L'action à effectuer [optional] :
     * <ul>
     * <li>LCFEISTEL_ADD : ajoute $noeud (string) à la fin de la liste (defaut)</li>
     * <li>LCFEISTEL_REPLACE : remplace par $noeud (string[])</li>
     * </ul>
     * @throws LcFeistel_Exception
     */
    public function set_noeud($noeud, $action = 1) {
        //Verif l'action
        if (!in_array($action, [0, 1, 2])) {
            throw new LcFeistel_Exception("Action invalide");
        }
        //Effectue l'action
        switch ($action) {
            case LCFEISTEL_ADD:
                if (!is_string($noeud)) {
                    throw new LcFeistel_Exception('$noeud n\'est pas un string');
                }
                $this->noeud[] = $noeud;
                break;
            case LCFEISTEL_REPLACE:
                if (!is_array($noeud)) {
                    throw new LcFeistel_Exception('$noeud n\'est pas un tableau');
                }
                $this->noeud = $noeud;
                break;
        }
    }

    /**
     * Récupère les fonctions ajoutées par l'utilisateur
     * @return function[]
     */
    public function get_function() {
        return $this->function;
    }

    /**
     * Modifie les fonctions ajoutées par l'utilisateur
     * @param string $name - Le nom de la fonction
     * @param function $function - La fonction
     * @param int $action - L'action à effectuer [optional] :
     * <ul>
     * <li>LCFEISTEL_NONE : n'ajoute pas la fonction dans l'attribut $noeud (defaut)</li>
     * <li>LCFEISTEL_ADD : ajoute dans l'attribut $noeud à la fin de la liste</li>
     * <li>LCFEISTEL_REPLACE : vide l'attribut $noeud et met la fonction</li>
     * </ul>
     * @throws LcFeistel_Exception
     */
    public function set_function($name, $function, $action = 0) {
        //Verif l'action
        if (!in_array($action, [0, 1, 2])) {
            throw new LcFeistel_Exception("Action invalide");
        }
        //Effectue l'action
        switch ($action) {
            case LCFEISTEL_NONE:
                $this->function[$name] = $function;
                break;
            case LCFEISTEL_ADD:
                $this->function[$name] = $function;
                $this->noeud[] = $name;
                break;
            case LCFEISTEL_REPLACE:
                $this->function[$name] = $function;
                $this->noeud = [$name];
                break;
        }
    }

    /**
     * Récupère la clef
     * @return int
     */
    public function get_key() {
        return $this->key;
    }

    /**
     * Modifie la clef
     * @param int $key
     * @throws LcFeistel_Exception
     */
    public function set_key($key) {
        if($key < 0){
            throw new LcFeistel_Exception("La clef n'est pas positive");
        }
        if (!$this->is_integer($key)) {
            throw new LcFeistel_Exception("La clef n'est pas un entier");
        }
        if(in_array($key, [1, 2, 3, 4])){
            throw new LcFeistel_Exception("La clef doit être un entier supérieur à 4");
        }
        $this->key = $key;
    }

    /**
     * Récupère le résultat de la dernière opération effectuée
     * @return int|float
     */
    public function get_last_operation() {
        return $this->last_operation;
    }

    /* === Les méthodes utilitaires === */

    /**
     * Indique si une données est un entier
     * @param int|string $nb
     * @return boolean
     */
    protected function is_integer($nb) {
        return ctype_digit(strval($nb));
    }

    /**
     * Ajoute la clef aux données
     * @param int $data
     * @return int
     */
    protected function add_key($data) {
        if ($this->key % 2 == 0) {
            $data += $this->key;
        } else {
            $data += $this->key - floor($this->key / 4);
        }
        if ((int) substr('' . $this->key, 0, 1) < 5) {
            $data *= floor($this->key / 10);
        } else {
            $data *= $this->key;
        }
        $data -= $this->key * (1 / $this->key);
        return $data;
    }

    /**
     * Retire la clef des données
     * @param int $data
     * @return int
     */
    protected function remove_key($data) {
        $data += $this->key * (1 / $this->key);
        if ((int) substr('' . $this->key, 0, 1) < 5) {
            $data /= floor($this->key / 10);
        } else {
            $data /= $this->key;
        }
        if ($this->key % 2 == 0) {
            $data -= $this->key;
        } else {
            $data -= $this->key - floor($this->key / 4);
        }
        return $data;
    }

    /**
     * Augemente la taille d'un nombre bianire pour qu'il soit composé de n bit mod $length = 0
     * @param string $bin - Le nombre binaire
     * @param int $length - La taille du nombre binaire [optional] (defaut bit_block_length)
     * @return string
     */
    protected function complete_bin($bin, $length = 0) {
        if($length == 0){
            $length = self::$bit_block_length * 2;
        }
        if (strlen($bin) % $length != 0) {
            $nbTour = $length - (strlen($bin) % $length);
            for ($i = 0; $i < $nbTour; $i++) {
                $bin = '0' . $bin;
            }
        }
        return $bin;
    }

    /**
     * Coupe un nombre binaire de taille n en tableau de nombre binaire de taille bit_block_length
     * @param string $bin - Le nombre binaire
     * @return string[]
     */
    protected function cut_bin($bin) {
        $tab = [];
        for ($i = 0; $i < strlen($bin); $i += self::$bit_block_length) {
            $tab[] = substr($bin, $i, self::$bit_block_length);
        }
        return $tab;
    }

    /**
     * Effectue un XOR entre deux nombre bianire
     * @param string $bin1
     * @param string $bin2
     * @return false|string
     */
    protected function bin_xor($bin1, $bin2) {
        if (strlen($bin1) != strlen($bin2)) {
            return false;
        }
        $binRes = '';
        for ($i = 0; $i < strlen($bin1); $i++) {
            if ($bin1[$i] == $bin2[$i]) {
                $binRes .= '0';
            } else {
                $binRes .= '1';
            }
        }
        return $binRes;
    }

    /* === Les méthodes de chiffrement basique de la class === */

    /*
     * Les fonctions qui suivent prennent toutes un nombre binaire de 2 bit en parametre
     * et renvoyent un nombre de 2 bit en sortie.
     * Les fonctions ajouté par l'utilisateur doivent avoir le même comportement pour bit_block_length = 2
     * Sinon elles doivent prendre un chaine de bit_block_length en parametre et retourner une chaine de bit_block_length
     */

    protected function f1($bin) {
        if(self::$bit_block_length != 2){
            throw new LcFeistel_Exception("La méthode f1 est fait pour être utilisée avec un bit_block_length = 2");
        }
        switch ($bin) {
            case '00':
                return '01';
            case '01':
                return '11';
            case '10':
                return '10';
            case '11':
                return '01';
        }
    }

    protected function f2($bin) {
        if(self::$bit_block_length != 2){
            throw new LcFeistel_Exception("La méthode f2 est fait pour être utilisée avec un bit_block_length = 2");
        }
        switch ($bin) {
            case '00':
                return '11';
            case '01':
                return '00';
            case '10':
                return '00';
            case '11':
                return '01';
        }
    }

    protected function f3($bin) {
        if(self::$bit_block_length != 2){
            throw new LcFeistel_Exception("La méthode f3 est fait pour être utilisée avec un bit_block_length = 2");
        }
        switch ($bin) {
            case '00':
                return '11';
            case '01':
                return '10';
            case '10':
                return '01';
            case '11':
                return '00';
        }
    }

    protected function f4($bin) {
        if(self::$bit_block_length != 2){
            throw new LcFeistel_Exception("La méthode f4 est fait pour être utilisée avec un bit_block_length = 2");
        }
        switch ($bin) {
            case '00':
                return '01';
            case '01':
                return '00';
            case '10':
                return '01';
            case '11':
                return '00';
        }
    }

}

class LcFeistel_Exception extends Exception {
    
}
