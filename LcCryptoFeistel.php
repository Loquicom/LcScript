<?php

/* =============================================================================
 * LcCryptoFeistel by Loquicom
 * Ver 1.2
 * =========================================================================== */

class LcCryptoFeistel extends LcFeistel {

	/**
	 * Taille max des chaines dans un fichier chiffré
	 * @var int
	 */
	protected static $file_split_length = 76;

	/**
	 * Clef de chiffrement
	 * @var string
	 */
	protected $key;

	/**
	 * Construction de l'objet
	 * @param string - La clef
	 */
	public function __construct($key){
		//Ajoute la clef
		$this->set_key($key);
		//Modifie l'attribut bit_block_length
		parent::set_bit_block_length(4);
	}

	/* === Les méthodes de (de)chiffrement === */

	/**
	 * Chiffre une chaine
	 * @param string $data - La chaine à chiffrer
	 * @return string - La chaine chiffré
	 * @throws LcFeistel_Exception
	 */
	public function crypt($data){
		//Verifie que data est bien un string
		if(!is_string($data)){
			throw new LcFeistel_Exception("Les données ne sont pas un string");
		}
		//Ajoute la clef
		$tabInt = $this->add_key($data);
		//Chiffre tous les carac
		$code = [];
		foreach ($tabInt as $int) {
			$code[] = parent::crypt($int);
		}
		//Remet sous forme de string le resultat
		$res = $this->code_to_string($code);
		//Sauvegarde resultat et retour
		$this->last_operation = $res;
		return $res;
	}

	/**
	 * Déchiffre une chaine
	 * @param string $data - La chaine à déchiffrer
	 * @return string - La chaine déchiffrée
	 * @throws LcFeistel_Exception
	 */
	public function decrypt($data){
		//Verifie que data est bien un string
		if(!is_string($data)){
			throw new LcFeistel_Exception("Les données ne sont pas un string");
		}
		//Recup le code
		$code = $this->string_to_code($data);
		//Déchiffre
		$tabInt = [];
		foreach ($code as $val) {
			$tabInt[] = parent::decrypt($val);
		}
		//Retire la clef
		$res = $this->remove_key($tabInt);
		//Sauvegarde resultat et retour
		$this->last_operation = $res;
		return $res;
	}

	/**
	 * Chiffre un fichier
	 * @param string $filename - Le chemin et le nom du fichier
	 * @param string $newname - Le chemin et le nom du fichier chiffré [optional]
	 * @return string - Le nom du nouveau fichier
	 * @throws LcFeistel_Exception
	 */
	public function crypt_file($filename, $newname = null){
		set_time_limit(0);
		//Verif que le fichier existe
		if(!file_exists($filename)){
			throw new LcFeistel_Exception("Le fichier est introuvable");
		}
		//Calcul nom du fichier
		if($newname === null){
			$name = explode('.', $filename);
			$name[count($name) - 1] = 'lccf';
			$name = implode('.', $name);
		} else {
			$name = $newname . '.lccf';
		}
		//Ouverture des fichiers
		$file = fopen($filename, 'r');
		if($file === false){
			throw new LcFeistel_Exception("Impossible d'ouvrir le fichier $filename");
		}
		$new = fopen($name, 'w');
		if($new === false){
			throw new LcFeistel_Exception("Impossible d'ouvrir/creer le fichier $name");
		}
		//Lecture > Chiffrement > Ecriture
		$data = '';
		$whl = true;
		while($whl !== false){
			//Lecture du fichier d'origine
			$tmp = fread($file, self::$file_split_length);
			if($tmp === false){
				throw new LcFeistel_Exception("Erreur lors de la lecture du fichier $filename");
			}
			if($tmp != ''){
				//Chiffrement
				try{
					$data .= $this->crypt($tmp) . '-lc';
				} catch(LcFeistel_Exception $lcfex){
					throw new LcFeistel_Exception("Erreur lors du chiffrement");
				}
			}
			//Ecriture dans le nouveau fichier
			$dataCrypt = substr($data, 0, self::$file_split_length);
			if(fwrite($new, $dataCrypt) === false){
				throw new LcFeistel_Exception("Erreur lors de l'écriture dans le fichier $name");
			}
			if(fwrite($new, "\r\n") === false){
				throw new LcFeistel_Exception("Erreur lors de l'écriture dans le fichier $name");
			}
			//Retrait de ce qui est deja dans le fichier
			$data = substr($data, self::$file_split_length);
			if($data === false){
				$whl = false;
			}
		}
		//Ferme les fichiers
		fclose($file);
		fclose($new);
		//Retourne le nom du nouveau fichier
		return $name;
	}

	/**
	 * Déchiffre un fichier chiffré par LcCryptoFeistel
	 * @param string $filename - Le chemin et le nom du fichier sans le .lccf
	 * @param string $newname - Le chemin et le nom du fichier déchiffré [optional]
	 * @return string - Le nom du nouveau fichier
	 * @throws LcFeistel_Exception
	 */
	public function decrypt_file($filename, $newname = null){
		set_time_limit(0);
		//Verif que le fichier existe
		if(!file_exists($filename . '.lccf')){
			throw new LcFeistel_Exception("Le fichier est introuvable");
		}
		//Calcul nom du nouveau fichier
		if($newname === null){
			$name = str_replace('.lccf', '', $filename);
		} else {
			$name = $newname;
		}
		//Ouverture des fichiers
		$file = fopen($filename . '.lccf', 'r');
		if($file === false){
			throw new LcFeistel_Exception("Impossible d'ouvrir le fichier $filename");
		}
		$new = fopen($name, 'w');
		if($new === false){
			throw new LcFeistel_Exception("Impossible d'ouvrir/creer le fichier $name");
		}
		//Lecture > Dehiffrement > Ecriture
		$data = '';
		$whl = true;
		$last = false;
		while($whl){
			//Lecture du fichier chiffré
			$data .= fread($file, self::$file_split_length);
			if($data === false){
				throw new LcFeistel_Exception("Erreur lors de la lecture du fichier $filename");
			}
			$data = str_replace("\r\n", "", $data);
			if($data != ''){
				//Pas le dernier tour
				$last = false;
			}
			//Si il y a une zone à déchiffrer
			if(strpos($data, '-lc') !== false){
				$tmp = substr($data, 0, strpos($data, '-lc'));
				//Dechiffrement
				try{
					$tmp = $this->decrypt($tmp);
				} catch(LcFeistel_Exception $lcfex){
					throw new LcFeistel_Exception("Erreur lors du déchiffrement");
				}
				//Ecriture dans le nouveau fichier
				if(fwrite($new, $tmp) === false){
					throw new LcFeistel_Exception("Erreur lors de l'écriture dans le fichier $name");
				}
				//Retire la partie déchifrée
				$data = substr($data, strpos($data, '-lc') + 3);
				if($data === false || trim($data) == ''){
					//Refait un tour pour être sur d'être à la fin	
					$last = true;
				}
			} else {
				//Si le dernier tour est fini on stop
				if($last){		
					$whl = false;
				}
			}	
		}
		//Ferme les fichiers
		fclose($file);
		fclose($new);
		//Retourne le nom du nouveau fichier
		return $name;
	}

	/* === Getter / Setter === */

	/**
	 * Récupère la taille max des chaines dans un fichier chiffré
	 * @return int - La taille 
	 */
	public static function get_file_split_length(){
		return self::$file_split_length;
	}

	/**
	 * Modifie la taille max des chaines dans un fichier chiffré
	 * @param int $length - La taille
	 */
	public static function set_file_split_length($length){
		//Verfie que la longeur est bien un int
		if(ctype_digit(strval($length))){
			throw new LcFeistel_Exception("La longueur n'est pas un string");
		}
		//Modifie
		self::$file_split_length = $length;
	}

	/**
     * Récupère la clef
     * @return string
     */
	public function get_key(){
		return $this->key;
	}

	/**
	 * Modifie la clef
	 * @param $key - La nouvelle clef
	 * @throws LcFeistel_Exception
	 */
	public function set_key($key){
		//Verfie que la clef est bien un string
		if(!is_string($key)){
			throw new LcFeistel_Exception("La clef n'est pas un string");
		}
		//Modifie la clef
		$this->key = $key;
	}

	/* === Les méthodes utilitaires === */

	/**
	 * Modifie les données avec la clef
	 * @param string $data
	 * @param string $key - La clef [optional] (defaut la clef de l'objet)
	 * @return int[] - Le code entier de chaque caractères
	 */
	protected function add_key($data, $key = null){
		//Recup la clef
		if($key === null){
			$key = $this->key;
		}
		//Calcul
		$lengthKey = strlen($key);
		$tabInt = [];
		for($i = 0; $i < strlen($data); $i++){
			$nbData = ord($data[$i]);
			$nbKey = ord($key[$i % $lengthKey]);
			$tabInt[] = parent::add_key($nbData, $nbKey);
		}
		return $tabInt;
	}

	/**
	 * Retire la modification des données avec la clef
	 * @param int[] $tabInt - Le code entier de chaque caractères
	 * @param string $key - La clef [optional] (defaut la clef de l'objet)
	 * @return string - Les données
	 */
	protected function remove_key($tabInt, $key = null){
		//Recup la clef
		if($key === null){
			$key = $this->key;
		}
		//Calcul
		$lengthKey = strlen($key);
		$data = '';
		for($i = 0; $i < count($tabInt); $i++){
			$nbKey = ord($key[$i % $lengthKey]);
			$nbData = parent::remove_key($tabInt[$i], $nbKey);
			$data .= chr($nbData);
		}
		return $data;
	}

	/**
	 * Transforme le résultat de l'encodage de chaque caractère en string
	 * @param float[] - Les caractères encoder
	 * @return string
	 */
	protected function code_to_string($code){
		return str_replace('=', '', (base64_encode(gzcompress(implode('¤', $code)))));
	}

	/**
	 * Transforme un string en tableau de caractère encodé
	 * @param string str
	 * @return float[]
	 */
	protected function string_to_code($str){
		$tmp = gzuncompress(base64_decode($str));
		return explode('¤', $tmp);
	}

	/* === Les méthodes de chiffrement basique de la class === */

	/*
     * Les fonctions qui suivent prennent toutes un nombre binaire de 4 bit en parametre
     * et renvoyent un nombre de 4 bit en sortie.
     * Les fonctions ajouté par l'utilisateur doivent avoir le même comportement
     */

	protected function f1($bin){
		$tmp = str_replace(array('1', '0'), array('2', '1'), $bin);
		return str_replace('2', '0', $tmp);
	}

	protected function f2($bin){
		$tmp = substr($bin, 0, 2);
		return $tmp . $tmp;
	}

	protected function f3($bin){
		$tmp0 = '';
		$tmp1 = '';
		for($i = 0; $i < strlen($bin); $i++){
			if($bin[$i] == '0'){
				$tmp0 .= '0';
			} else {
				$tmp1 .= '1';
			}
		}
		return $tmp0 . $tmp1;
	}

	protected function f4($bin){
		return parent::bin_xor(substr($bin, 0, 2), substr($bin, 2)) . parent::bin_xor($bin[0] . $bin[3], $bin[1] . $bin[2]);
	}

}
