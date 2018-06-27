<?php

/* =============================================================================
 * LcLog by Loquicom
 * Ver 1.2
 * =========================================================================== */

Class LcLog {

    /**
     * Indique si l'on remplace automatiquement ou non le log courant par ceux 
     * indiqué dans les methodes
     * @var boolean
     */
    static private $replace = false;

    /**
     * Les emails pour envoyer
     * @var string[]
     */
    private static $email = null;

    /**
     * Fonction pour envoyer les emails
     * @var function
     */
    private static $emailFunction = null;

    /**
     * Le nom du log
     * @var string 
     */
    private $name;

    /**
     * Ressource du fichier de log
     * @var ressource
     * @see fopen 
     */
    private $file;

    /**
     * Ressource du fichier de trace
     * @var ressource
     * @see fopen 
     */
    private $trace = null;

    /**
     * Le chemin du fichier
     * @var string
     */
    private $path;

    /**
     * Le contenue du log
     * @var mixed 
     */
    private $content;

    /**
     * La clef du dernier log utilisé
     * @var string
     */
    private $lastLog;

    /**
     * Les balises actives pour chaque log
     * @var mixed
     */
    private $balise;

    /**
     * Le temp d'execution des scripts
     * @var int
     */
    private $executionTime;

    /**
     * Indique si le log est fini
     * @var boolean
     */
    private $stop = false;

    /**
     * La derniere instance de logs crée
     * @var Logs
     */
    private static $instance = null;

    /**
     * Instancie un objet Logs
     * @param string $name - Le nom du log
     * @param string $path - Le chemin pour créer le fichier
     * @param boolean $keep - Garder le contenue du fichier si il existe deja (defaut true)
     * @param boolean $trace - Garde une trace de l'ordre des actions
     * @throws LogException
     */
    public function __construct($name, $path = './lclog.log', $keep = true, $trace = true) {
        if (trim($name) == '') {
            throw new LogException('Nom invalide');
        }
        //Initialisation des varaibles d'instances
        $this->name = $name;
        $this->path = $path;
        $this->content = array();
        $this->balise = array();
        $this->executionTime = array();
        //Verification de l'existence du fichier
        if (file_exists($path)) {
            //Si il n'est pas lisibe
            if (!is_writable($path)) {
                throw new LogException('Impossible d\'écrire dans le fichier : ' . $path);
            }
            //Si on garde l'ancien contenue
            if ($keep) {
                $this->content['initial'] = file_get_contents($path);
            }
        }
        //Verifie que le dossier de destination du fichier existe
        if (!file_exists(dirname($path)) && dirname($path) != '.') {
            //Création du dossier
            $res = self::createDir(dirname($path));
        }
        //Ouverture du fichier
        $this->file = fopen($path, 'w');
        //On verifie que l'ouverture c'est bien passé
        if ($this->file === false) {
            throw new LogException('Impossible d\'ouvrir le fichier : ' . $path);
        }
        //Si on garde une trace temps réel
        if ($trace) {
            //Calcul du chemin
            $tracePath = explode('.', $path);
            if (count($tracePath) > 1) {
                $tracePath[count($tracePath) - 1] = 'log';
                $tracePath[count($tracePath) - 2] .= '-trace';
                $tracePath = implode('.', $tracePath);
            } else {
                $tracePath = $path . '.log';
            }
            //Verification de l'existence du fichier
            $initialTrace = '';
            if (file_exists($tracePath)) {
                //Si il n'est pas lisibe
                if (!is_writable($tracePath)) {
                    throw new LogException('Impossible d\'écrire dans le fichier : ' . $path);
                }
                //Si on garde l'ancien contenue
                if ($keep) {
                    $initialTrace = file_get_contents($tracePath);
                }
            }
            //Ouverture du fichier
            $this->trace = fopen($tracePath, 'w');
            //On verifie que l'ouverture c'est bien passé
            if ($this->trace === false) {
                throw new LogException('Impossible d\'ouvrir le fichier : ' . $tracePath);
            }
            //On ajoute le contenue initial
            fwrite($this->trace, $initialTrace);
        }
        //On set la derniere instance
        static::$instance = $this;
        //On parametre la gestion d'erreur
        register_shutdown_function("Logs::shutdown");
        set_error_handler("Logs::error_handler");
    }

    /**
     * Demare un log
     * @param string $nom - Le nom du log
     * @param boolean $multiBalise - Affiche une ou plusieurs balise dans la ligne de début (dfaut false -> une seul balise)
     * @param string $timeZone - La timezone pour les dates
     * @return string - Le nom du log
     * @throws LogException
     */
    public function startLog($nom = '', $multiBalise = false, $timeZone = '') {
        //Verif que log est toujours actif
        if ($this->stop) {
            throw new LogException('Le fichier de log est terminé, et ne peut plus être modifié');
        }
        //Methode
        if (trim($nom) == '') {
            $nom = 'Log ' . count($this->content);
        }
        if (trim($timeZone) != '') {
            @date_default_timezone_set($timeZone);
        }
        //Parametre pour les balises
        $this->balise[$nom] = array('ERR' => false, 'WARN' => false, 'CONF' => false, 'INFO' => false, 'multi' => $multiBalise, 'nombre' => array('ERR' => 0, 'WARN' => 0, 'CONF' => 0, 'INFO' => 0));
        //Creation du contenue
        $this->content[$nom] = '----- ___BAL___ Début du log : ' . $nom . ' (' . $this->name . ') <' . date('Y-m-d H:i:s') . '> -----' . "\r\n";
        //Sauvegarde de la derniere clef
        $this->lastLog = $nom;
        //Debut du décompte
        $this->executionTime[$nom] = microtime(true);
        //Ajout trace
        $this->trace('start', 'Création du log : ' . $nom);
        //Retour
        return $nom;
    }

    /**
     * 
     * @param string $content - Le contenue de la ligne
     * @param string $balise - Balise pour la ligne parmis ERR, WARN, CONF, INFO
     * @param string $nom - Le nom du contenue (laissé vide pour utiliser le dernier commencer ou parametré)
     * @param boolean $date - Afficher la date (defaut true)
     * @return $this
     * @throws LogException
     */
    public function addLine($content, $balise = '', $nom = '', $date = true) {
        //Verif que log est toujours actif
        if ($this->stop) {
            throw new LogException('Le fichier de log est terminé, et ne peut plus être modifié');
        }
        //Si on veux ecrire dans un log different de celui en cours
        if (trim($nom) != '') {
            if (!isset($this->content[$nom])) {
                throw new LogException('Log introuvable');
            }
            //Changement du log courant si replace
            (self::$replace) ? $this->lastLog = $nom : null;
        } else {
            if ($this->lastLog === null) {
                throw new LogException('Aucun ouvert');
            }
            $nom = $this->lastLog;
        }
        //Verif que endlog pas deja appelé
        if (strpos($this->content[$nom], '___FIN-' . $nom . '___') !== false) {
            throw new LogException('Log déjà fini');
        }
        //On indique ou non la date
        if ($date) {
            $this->content[$nom] .= '<' . date('Y-m-d H:i:s') . '> : ';
        }
        //On affiche le type si il est present
        $balise = strtoupper($balise);
        if (in_array($balise, array('ERR', 'WARN', 'CONF', 'INFO'))) {
            $this->content[$nom] .= '[' . $balise . '] - ';
            $this->balise[$nom][$balise] = true;
            $this->balise[$nom]['nombre'][$balise] ++;
        }
        $this->content[$nom] .= $content . "\r\n";
        //Ajout trace
        $this->trace('add', $nom . ' : (' . $balise . ') ' . $content);
        //Ce retourne sois meme pour permettre d'ajouter plusieurs ligne d'affilé
        return $this;
    }

    /**
     * Met fin à un contenue
     * @param string $nom - Le nom du contenue (laissé vide pour utiliser le dernier commencer ou parametré)
     * @param boolean $write - Ecrire dans le fichier (defaut false)
     * @throws LogException
     */
    public function endLog($nom = '', $write = false) {
        //Verif que log est toujours actif
        if ($this->stop) {
            throw new LogException('Le fichier de log est terminé, et ne peut plus être modifié');
        }
        //Si on veux finir un log different de celui en cours
        if (trim($nom) != '') {
            if (!isset($this->content[$nom])) {
                throw new LogException('Log introuvable');
            }
            //Changement du log courant si replace
            (self::$replace) ? $this->lastLog = $nom : null;
        } else {
            $nom = $this->lastLog;
        }
        //Verif que endlog pas deja appelé
        if (strpos($this->content[$nom], '___FIN-' . $nom . '___') !== false) {
            throw new LogException('Log déjà fini');
        }
        //Ligne de fin
        $nombreBalise = '';
        foreach ($this->balise[$nom]['nombre'] as $balise => $nombre) {
            $nombreBalise .= ' ' . $nombre . ' [' . $balise . '],';
        }
        $nombreBalise = rtrim($nombreBalise, ',');
        $this->content[$nom] .= '----- Fin du log : ' . $nom . ' -' . $nombreBalise . ' (Temps d\'exécution ' . (self::formatTemps(microtime(true) - $this->executionTime[$nom])) . ') <' . date('Y-m-d H:i:s') . '> -----___FIN-' . $nom . '___' . "\r\n";
        //Si demander on écris dans le fichier
        if ($write) {
            $this->writeLog($nom, false, false);
        }
        //Ajout trace
        $this->trace('end', 'Fin du log : ' . $nom);
    }

    /**
     * Ecris le contenue dans le fichier (uniquement les logs terminés)
     * @param string $nom - Le nom du contenue à écrire (si vide, tous les contenues)
     * @param boolean $sendMail - Envoyer un mail recapitulatif (defaut false)
     * @param boolean $close - Fermer apres l'ecriture (defaut true)
     * @throws LogException
     */
    public function writeLog($nom = '', $sendMail = false, $close = true) {
        //Verif que log est toujours actif
        if ($this->stop) {
            throw new LogException('Le fichier de log est terminé, et ne peut plus être modifié');
        }
        //Si il y a un contenue initial on le met
        if (isset($this->content['initial'])) {
            fwrite($this->file, $this->content['initial'] . "\r\n");
        }
        //Contenue du fichier
        if (trim($nom) != '') {
            if (!isset($this->content[$nom])) {
                throw new LogException('Log introuvable');
            } else if (strpos($this->content[$nom], '___FIN-' . $nom . '___') !== false) {
                throw new LogException('Log déjà fini');
            }
            //Changement du log courant si replace
            (self::$replace) ? $this->lastLog = $nom : null;
            //On remplace les reperes
            $balise = '';
            if ($this->balise[$nom]['multi']) {
                if ($this->balise[$nom]['ERR']) {
                    $balise .= '[ERR]';
                }
                if ($this->balise[$nom]['WARN']) {
                    $balise .= '[WARN]';
                }
                if ($this->balise[$nom]['CONF']) {
                    $balise .= '[CONF]';
                }
                if ($this->balise[$nom]['INFO']) {
                    $balise .= '[INFO]';
                }
            } else {
                if ($this->balise[$nom]['ERR']) {
                    $balise .= '[ERR]';
                } else if ($this->balise[$nom]['WARN']) {
                    $balise .= '[WARN]';
                } else if ($this->balise[$nom]['CONF']) {
                    $balise .= '[CONF]';
                } else if ($this->balise[$nom]['INFO']) {
                    $balise .= '[INFO]';
                }
            }
            $this->content[$nom] = str_replace('___BAL___', $balise, $this->content[$nom]);
            $this->content[$nom] = str_replace('___FIN-' . $nom . '___', '', $this->content[$nom]);
            fwrite($this->file, $this->content[$nom] . "\r\n");
        } else {
            foreach ($this->content as $nom => $content) {
                //On s'occupe uniquement des log fini
                if (strpos($content, '___FIN-' . $nom . '___') !== false) {
                    if ($nom !== 'initial') { //On ne touche pas au contenue initial
                        //On remplace les reperes
                        $balise = '';
                        if ($this->balise[$nom]['multi']) {
                            if ($this->balise[$nom]['ERR']) {
                                $balise .= '[ERR]';
                            }
                            if ($this->balise[$nom]['WARN']) {
                                $balise .= '[WARN]';
                            }
                            if ($this->balise[$nom]['CONF']) {
                                $balise .= '[CONF]';
                            }
                            if ($this->balise[$nom]['INFO']) {
                                $balise .= '[INFO]';
                            }
                        } else {
                            if ($this->balise[$nom]['ERR']) {
                                $balise .= '[ERR]';
                            } else if ($this->balise[$nom]['WARN']) {
                                $balise .= '[WARN]';
                            } else if ($this->balise[$nom]['CONF']) {
                                $balise .= '[CONF]';
                            } else if ($this->balise[$nom]['INFO']) {
                                $balise .= '[INFO]';
                            }
                        }
                        $content = str_replace('___BAL___', $balise, $content);
                        $content = str_replace('___FIN-' . $nom . '___', '', $content);
                        fwrite($this->file, $content . "\r\n");
                    }
                }
            }
        }
        //Ajout trace
        $this->trace('write', 'Écriture du log : ' . $nom);
        //Si envoie par mail
        if ($sendMail) {
            $this->sendMail();
        }
        //Si fermeture du fichier
        if ($close) {
            $this->stop();
        }
    }

    /**
     * Ferme le fichier et mets fin à l'écriture du log
     * @return boolean
     * @throws LogException
     */
    public function stop() {
        //Verif que log est toujours actif
        if ($this->stop) {
            throw new LogException('Le fichier de log est terminé, et ne peut plus être modifié');
        }
        //Ajout trace
        $this->trace('stop', 'Ferme le fichier de log');
        //Methode
        return fclose($this->file);
    }

    public function sendMail($mail = '', $function = null) {
        //Si c'est un tableau d'email en parametre
        if (is_array($mail)) {
            //Si il es vide on prend celui de l'instance
            if (empty($mail)) {
                //Si il y en a qui sont renseigné dans l'instance
                if (self::$email === null) {
                    throw new LogException('Aucune adresse e-mail');
                }
                $mail = self::$email;
            } else {
                //Sinon on verifie chaque email du tableau
                foreach ($mail as $val) {
                    if (!self::isMail($val)) {
                        throw new LogException('E-mail incorrect : ' . $val);
                    }
                }
            }
        }
        //Sinon si c'est un string
        else if (is_string($mail)) {
            //Si il est vide on utilise celui de l'instance
            if (trim($mail) == '') {
                //Si il y en a qui sont renseigné dans l'instance
                if (self::$email === null) {
                    throw new LogException('Aucune adresse e-mail');
                }
                $mail = self::$email;
            } else {
                //Verif mail
                if (!self::isMail($mail)) {
                    throw new LogException('E-mail incorrect : ' . $mail);
                }
                //Mise dans un tableau
                $mail = array($mail);
            }
        }
        //Sinon erreur
        else {
            throw new LogException('Parametre email invalide');
        }
        //Generation des parametres pour l'envoi
        $list = array();
        $baliseTotal = ''; //Pour indiquer la plus haute balise de tous les logs
        foreach ($this->content as $nom => $content) {
            if ($nom != 'initial') {
                $balise = '';
                if ($this->balise[$nom]['multi']) {
                    if ($this->balise[$nom]['ERR']) {
                        $balise .= '[ERR]';
                        $baliseTotal = 'ERR';
                    }
                    if ($this->balise[$nom]['WARN']) {
                        $balise .= '[WARN]';
                        ($baliseTotal != 'ERR') ? $baliseTotal = 'WARN' : null;
                    }
                    if ($this->balise[$nom]['CONF']) {
                        $balise .= '[CONF]';
                        ($baliseTotal != 'ERR' && $baliseTotal != 'WARN') ? $baliseTotal = 'CONF' : null;
                    }
                    if ($this->balise[$nom]['INFO']) {
                        $balise .= '[INFO]';
                        ($baliseTotal != 'ERR' && $baliseTotal != 'WARN' && $baliseTotal != 'CONF') ? $baliseTotal = 'INFO' : null;
                    }
                } else {
                    if ($this->balise[$nom]['ERR']) {
                        $balise .= '[ERR]';
                        $baliseTotal = 'ERR';
                    } else if ($this->balise[$nom]['WARN']) {
                        $balise .= '[WARN]';
                        ($baliseTotal != 'ERR') ? $baliseTotal = 'WARN' : null;
                    } else if ($this->balise[$nom]['CONF']) {
                        $balise .= '[CONF]';
                        ($baliseTotal != 'ERR' && $baliseTotal != 'WARN') ? $baliseTotal = 'CONF' : null;
                    } else if ($this->balise[$nom]['INFO']) {
                        $balise .= '[INFO]';
                        ($baliseTotal != 'ERR' && $baliseTotal != 'WARN' && $baliseTotal != 'CONF') ? $baliseTotal = 'INFO' : null;
                    }
                }
                $list[] = $balise . ' ' . $nom;
            }
        }
        //Ajout trace
        $this->trace('email', 'Envoie du log par email');
        //Si une fonction d'envoie est definie on l'utilise, sinon on utilise celle de cette class
        if ($function !== null || self::$emailFunction !== null) {
            //Si fonction est null on prend celle de la class
            ($function === null) ? $function = self::$emailFunction : null;
            return $function($mail, $this->name, $this->path, $list, $baliseTotal);
        } else {
            //Variable pour la création du mail
            $passage_ligne = "\r\n";
            $separator = md5(rand());
            //Header du mail
            $header = "From: LcLog <log@lcscript.com>" . $passage_ligne;
            $header .= "Mime-Version: 1.0" . $passage_ligne;
            $header .= "X-Priority: " . $this->priority . $passage_ligne;
            $header .= "X-Mailer: LcLog 1.2 " . $passage_ligne;
            $header .= "Date:" . date("D, d M Y H:s:i") . " +0200" . $passage_ligne;
            $header .= "Content-Transfer-Encoding: 7bit" . $passage_ligne;
            $header .= "Content-Type: multipart/mixed;boundary=" . $separator . $passage_ligne;
            //Message
            $message .= "Content-Type: text/plain; charset=\"utf-8\"" . $passage_ligne;
            $message .= "Content-Transfer-Encoding: 8bit" . $passage_ligne;
            $message .= "Liste des logs :" . $passage_ligne;
            foreach ($list as $val) {
                $message .= "   - " . $val . $passage_ligne;
            }
            //PJ
            $content = chunk_split(base64_encode(file_get_contents($this->path)));
            $message .= "--" . $separator . $passage_ligne;
            $message .= "Content-Type: text/plain; name=\"" . basename($this->path) . "\"" . $passage_ligne;
            $message .= "Content-Transfer-Encoding: base64" . $passage_ligne;
            $message .= "Content-Disposition: attachment" . $passage_ligne;
            $message .= $content . $passage_ligne;
            //Fin du message
            $message .= "--" . $separator . "--" . $passage_ligne;
            return mail($mail, 'Log ' . $this->name, $message, $header);
        }
    }

    /**
     * Ajout d'une trace dans le fichier trace
     * @param string $action - L'action effectué
     * @param string $text - le texte à mettre
     * @return boolean
     */
    public function trace($action, $text) {
        //Verifie que la trace est ouverte
        if ($this->trace === null) {
            return false;
        }
        //Ecriture dans le fichier
        $content = '<' . date('Y-m-d H:i:s') . '> ';
        $content .= '[' . strtoupper($action) . '] - ';
        $content .= $text . "\r\n";
        return (bool) fwrite($this->trace, $content);
    }

    /**
     * Met fin à la trace
     */
    public function endTrace() {
        $this->trace('fin', 'Fin de la trace');
        return fclose($this->trace);
    }

    /**
     * Retourne les balises et leur nombre pour un ou plusieurs logs
     * @param string $log - Nom du log (vide pour tous)
     * @return false|mixed - Le nombre de balise par type pour chaque log ou false en cas d'erreur
     */
    public function getBalise($log = '') {
        //si l'on veux les balises d'un log en particulier
        if (trim($log) != '') {
            if (isset($this->balise[$log])) {
                return $this->balise[$log]['nombre'];
            } else {
                //Si pas trouvé false
                return false;
            }
        }
        //Sinon retourne les balises de tous les logs
        $return = array();
        foreach ($this->balise as $logName => $val) {
            $return[$logName] = $val['nombre'];
        }
        return $return;
    }

    /**
     * Renvoie le contenue initial du fichier
     * @return false|string
     */
    public function getInitial() {
        if (isset($this->content['initial'])) {
            return $this->content['initial'];
        }
        return false;
    }

    /**
     * Change le log courrant
     * @param string $nom - Le nom du nouveau log courant
     * @throws LogException
     */
    public function setLog($nom) {
        //Verif existence
        if (!isset($this->content[$nom])) {
            throw new LogException('Log introuvable');
        }
        //Verif qu'il n'est pas fini
        else if (strpos($this->content[$nom], '___FIN-' . $nom . '___') !== false) {
            throw new LogException('Log déjà fini');
        }
        //Changement
        $this->lastLog = $nom;
    }

    /**
     * Indique si des emails sont set
     * @return boolean
     */
    public function isSetEmail() {
        return self::$email !== null;
    }

    /**
     * Change la valeur du paramtre de class replace
     * @param boolean $bool
     */
    public static function setReplace($bool) {
        self::$replace = (boolean) $bool;
    }

    /**
     * Change la liste des emails pour envoyer les logs
     * @param string[]|string $mail
     */
    public static function setEmail($mail) {
        //Si c'est une chaine de caractere qui est un mail
        if (is_string($mail) && self::isMail($mail)) {
            self::$email = array($mail);
        }
        //Si c'est un tableau
        else if (is_array($mail)) {
            //On verifie tous les champs du tableau
            foreach ($mail as $val) {
                if (!self::isMail($val)) {
                    throw new LogException('E-mail incorrect : ' . $val);
                }
            }
            self::$email = $mail;
        }
        //sinon erreur
        else {
            throw new LogException('E-mail incorrect : ' . $mail);
        }
    }

    /**
     * Change la fonction d'envoie de mail
     * @param function $function
     */
    public static function setEmailFunction($function) {
        self::$emailFunction = $function;
    }

    /**
     * Création d'un dossier recursivement
     * @param string $path Le chemin du dossier à créer
     * @return boolean
     */
    private static function createDir($path) {
        //Si le dossier n'existe pas
        if (!is_dir($path)) {
            //Tentative de création
            if (self::createDir(dirname($path))) {
                @mkdir($path);
            }
            //Erreur lors de la creation
            else {
                return false;
            }
        }
        //Le dossier est la
        return true;
    }

    /**
     * Verifie si une adresse email est correcte
     * @param string $email
     * @return boolean
     */
    private static function isMail($email) {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Transforme un nombre de micro seconde en format Y-m-d H:i:s.u
     * @param double $time - Les microsecondes 
     * @return string
     */
    private static function formatTemps($time) {
        $micro = sprintf("%06d", ($time - floor($time)) * 1000000);
        //Correction de l'heure en trop
        $date = date('H:i:s.' . $micro, floor($time));
        //Exctraction et retrait de l'heure
        $heure = (((int) substr($date, 0, 2)) - 1) % 24;
        //Ajustement
        if ($heure == 0) {
            $heure = '00';
        } else if ($heure < 10) {
            $heure = '0' . $heure;
        }
        //Remplacement
        $date = str_replace(substr($date, 0, 2), $heure, $date);
        return $date;
    }

    /**
     * Methode appelé lors de l'arret 
     * @return boolean
     */
    public static function shutdown() {
        //On recup la derniere instance
        $log = static::$instance;
        //Si pb pendant ajout dans le log
        try {
            //On ajoute une ligne de fin
            $content = "Execution interompue";
            $log->addLine($content, 'info');
            //Fin et ecriture du log
            $log->endLog();
            $log->writeLog();
            //Si email n'est pas null on envoie par email
            if ($log->isSetEmail()) {
                $log->sendMail();
            }
        } catch (Exception $ex) {
            $log->trace('shutdown', 'Erreur ecriture dans le log : ' . $ex->getMessage());
        }
        //Retour false pour affichage normal
        return true;
    }

    /**
     * Methode appelé en cas d'erreur
     * @param int $errno - Le type d'erreur
     * @param string $errstr - Le libelle de l'erreur
     * @param string $errfile - Le fichier de l'erreur
     * @param int $errline - Le ligne de l'erreur
     */
    public static function error_handler($errno, $errstr, $errfile, $errline) {
        //On recup la derniere instance
        $log = static::$instance;
        //Traitement des données de l'erreur
        $libErr = "";
        switch ($errno) {
            case E_NOTICE:
                $liberr = "E_NOTICE";
                break;
            case E_STRICT:
                $liberr = "E_STRICT";
                break;
            case E_USER_NOTICE:
                $liberr = "E_USER_NOTICE";
                break;
            case E_WARNING:
                $liberr = "E_WARNING";
                break;
            case E_USER_WARNING:
                $liberr = "E_USER_WARNING";
                break;
            case E_DEPRECATED:
                $liberr = "E_DEPRECATED";
                break;
            case E_USER_DEPRECATED:
                $liberr = "E_USER_DEPRECATED";
                break;
            case E_ERROR:
                $liberr = "E_ERROR";
                break;
            case E_USER_ERROR:
                $liberr = "E_USER_ERROR";
                break;
            default:
                $liberr = "E_NUMERO" . $errno;
                break;
        }
        $content = "Erreur (" . $libErr . ") : " . $errstr . " sur " . $errfile . " ligne " . $errline;
        //Si pb pendant ajout dans le log
        try {
            //On ajoute l'erreur
            $log->addLine($content, 'err');
            //Fin et ecriture du log
            $log->endLog();
            $log->writeLog();
            //Si email n'est pas null on envoie par email
            if ($log->isSetEmail()) {
                $log->sendMail();
            }
        } catch (Exception $ex) {
            $log->trace('error_handler', 'Erreur ecriture dans le log : ' . $ex->getMessage());
        }
        //Retour false pour affichage normal
        return false;
    }

}

/**
 * Class des exception de Logs
 */
Class LogException extends Exception {
    
}
