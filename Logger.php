<?php
/* ==============================================================================
  Fraquicom [PHP Framework] by Loquicom <contact@loquicom.fr>

  GPL-3.0
  Logger.php
  ============================================================================ */
defined('FC_INI') or exit('Acces Denied');

class Logger {

    /* --- Constantes --- */
    const NONE = 0;
    const INFO = 1;
    const WARN = 2;
    const ERR = 3;
    const CRIT = 4;
    const FATL = 5;

    /* --- Static --- */
    protected static $only_trace = false;

    /* --- Attributs --- */
    /**
     * Les noms des logs
     * @param string[]
     */
    protected $name = [];

    /**
     * Le nom du log actif
     * @param string
     */
    protected $active = null;

    /**
     * Le nom des fichiers des logs
     * @param string[]
     */
    protected $file = [];

    /**
     * Les ressources descripteur de fichier des logs
     * @param ressource[]
     */
    protected $fd = [];

    /**
     * Les ressources descripteur de fichier des traces des logs
     * @param ressource[]
     */
    protected $fd_trace = [];

    /**
     * Le contenue des logs
     * @param string[]
     */
    protected $log = [];

    /**
     * Le timestamp de debut de log
     * @param int[]
     */
    protected $time = [];

    /**
     * Les differents niveaux d'alertes des logs
     * @param array
     */
    protected $level = [];

    /**
     * Logs finient ou non
     * @param bool[]
     */
    protected $end = [];

    /**
     * Création d'un système de gestion de log avec un log
     * 
     * @param string $name Le nom du log (optional)
     * @param string $file Le nom du fichier de log sans extension (optional)
     * @param bool $overwrite Supprime ou non le contenue du fichier si il existe (optional)
     */
    public function __construct(string $name = null, string $file = null, bool $overwrite = false) {
        //Verification arguments
        if($name === null || trim($name) === '') {
            $name = 'Main';
        }
        if($file === null || trim($file) === '') {
            $file = 'log-' . date('dmY-His');
        } 
        //Recup des parametres
        $this->name[$name] = $name;
        //Ouverture fichier
        $this->open_file($name, $file, $overwrite);
        $this->end[$name] = false;
        $this->active = $name;
        $this->begin_log($name);
    }

    /**
     * Création d'un nouveau log
     * 
     * @param string $name Le nom du log (optional)
     * @param string $file Le nom du fichier de log sans extension (optional)
     * @param bool $overwrite Supprime ou non le contenue du fichier si il existe (optional)
     * @param bool $active Rend le nouveau log actif
     */
    public function new($name, $file = null, $overwrite = false, $active = true) {
        //Verif que le nom du log n'est pas deja utilisé
        if($this->log_exist($name)) {
            throw new LoggerException("Name of the log already in use");
        }
        //Verif que le nom du fichier n'est pas deja utilisé
        if($file !== null && in_array($file, $this->file)) {
            throw new LoggerException("File of the log already in use");
        }
        //Creation du nouveau log
        $this->name[$name] = $name;
        $this->open_file($name, $file, $overwrite);
        $this->end[$name] = false;
        $this->begin_log($name);
        //Si on le rend actif
        if($active) {
            $this->active = $name;
        }
    }

    /**
     * Ajoute une ligne dans le log
     * Alias de Logger::log
     * 
     * @param string La ligne à ajouter
     * @param int La constante correspondant au noiveau de gravité (optional)
     */
    public function add(string $log, int $level = self::NONE) {
        $this->log($log, $level);
    }

    /**
     * Ajoute une ligne dans le log
     * 
     * @param string La ligne à ajouter
     * @param int La constante correspondant au noiveau de gravité (optional)
     */
    public function log(string $log, int $level = self::NONE) {
        $this->not_end();
        //Creation ligne
        $line = $this->level_to_string($level);
        if($line !== '') {
            $line .= ' - ';
        }
        $line .= date('Y-m-d H:i:s') . ' (' . time() . ') - ' . $log . PHP_EOL;
        //Ecriture dans la trace
        if(fwrite($this->fd_trace[$this->active], $line) === false) {
            throw new LoggerException("Unable to write");
        }
        if(!self::$only_trace) {
            //Si il est vide on set le debut
            if($this->log[$this->active] === '') {
                $this->begin_log($this->active);
            }
            //Ajout de la ligne dans le log
            $this->log[$this->active] .= $line;
            if(isset($this->level[$this->active][$level])) {
                $this->level[$this->active][$level]++;
            }
            if($level > $this->level[$this->active]['WORST']) {
                $this->level[$this->active]['WORST'] = $level;
            }
        }
    }

    /**
     * Ecrit dans le fichier de log
     * 
     * @param string $name Le nom du log à écrire, null pour tous écrire
     */
    public function write(string $name = null) {
        if(self::$only_trace) {
            return;
        }
        //Si aucun nom on écrit tous les logs
        if($name === null || trim($name) === '') {
            foreach($this->name as $name) {
                $this->write($name);
            }
            return;
        }
        //Verif que le log n'est pas vide, finit ou le fd null
        $this->not_end($name);
        if($this->fd[$name] === null || $this->log[$name] === '' || strlen($this->log[$name]) == 56 + strlen($name)) {
            return;
        }
        //Ecriture du log
        $bilan = '--- LEVEL ' . 
        $this->level_to_string(self::INFO) . ' => ' . $this->level[$name][self::INFO] . ', ' .
        $this->level_to_string(self::WARN) . ' => ' . $this->level[$name][self::WARN] . ', ' .
        $this->level_to_string(self::ERR) . ' => ' . $this->level[$name][self::ERR] . ', ' .
        $this->level_to_string(self::CRIT) . ' => ' . $this->level[$name][self::CRIT] . ', ' .
        $this->level_to_string(self::FATL) . ' => ' . $this->level[$name][self::FATL] . ' ' .
        ' ---' . PHP_EOL;
        $end = '===== END : ' . $name . ' ' . $this->level_to_string($this->level[$name]['WORST']) . ' ' . $this->second_to_time(time() - $this->time[$name]) . ' (' . date('Y-m-d H:i:s') . ' - ' . $this->time[$name] . ') =====' . PHP_EOL;
        if(fwrite($this->fd[$name], $this->log[$name] . $bilan . $end . PHP_EOL) === false) {
            throw new LoggerException("Unable to write");
        }
        //Reset variable
        $this->log[$name] = '';
    }

    /**
     * Ferme les fichiers de log
     * 
     * @param string $name Le nom du log à finir, null pour tous écrire
     * @param bool $write Ecrire avant de fermer
     */
    public function end(string $name = null, bool $write = false) {
        //Si aucun nom on ferme tous les logs
        if($name === null || trim($name) === '') {
            foreach($this->name as $name) {
                $this->end($name, $write);
            }
            return;
        }
        //Log pas deja fermé
        $this->not_end($name);
        //Si on écrit le log avant de le fermer
        if($write) {
            $this->write($name);
        }
        //Fermeture des fichiers
        if(get_resource_type($this->fd_trace[$name]) !== 'Unknown') {
            fclose($this->fd_trace[$name]);
        }
        if($this->fd[$name] !== null && get_resource_type($this->fd[$name]) !== 'Unknown') {
            fclose($this->fd[$name]);
        }
        $this->end[$name] = true;
        unset($this->name[$name]);
    }

    /**
     * Change le log actif
     * 
     * @param string $name Le nom du log
     */
    public function set_active_log(string $name) {
        if(!$this->log_exist($name)) {
            throw new LoggerException("Unable to find log : " . $name);
        }
        $this->active = $name;
    }

    /**
     * Retourne le nom du log
     * @return string
     */
    public function get_active_log() {
        return $this->active;
    }

    /* --- Fonction static --- */

    /**
     * Active uniquement le log de trace
     * 
     * @param bool $trace
     */
    public static function set_only_trace(bool $trace) {
        self::$only_trace = $trace;
    }

    /* --- Fonctions privées --- */

    protected function open_file(string $name, string $file = null, bool $overwrite = false) {
        //Si file est null et qu'il n'y a pas plus de fichier ouvert
        if($file === null && (!isset($this->fd_tace[$this->active]) || $this->fd_tace[$this->active] === null || get_resource_type($this->fd_tace[$this->active]) === 'Unknown')) {
            $file = $this->file[$this->active];
        }
        //Creation fichier
        if($file === null) {
            $this->file[$name] = $this->file[$this->active];
            $this->fd_trace[$name] = $this->fd_trace[$this->active];
            if(self::$only_trace) {
                $this->fd[$name] = null;
            } else {
                $this->fd[$name] = $this->fd[$this->active];
            }
        } else {
            $this->file[$name] = $file;
            if($overwrite) {                
                $fd_trace = fopen($file . '-trace.log', 'w');
                if(self::$only_trace) {
                    $fd = null;
                } else {
                    $fd = fopen($file . '.log', 'w');
                }
            } else {
                $fd_trace = fopen($file . '-trace.log', 'a');
                if(self::$only_trace) {
                    $fd = null;
                } else {
                    $fd = fopen($file . '.log', 'a');
                }
            }
            if($fd === false || $fd_trace === false) {
                throw new LoggerException("Unable to open log files");
            }
            $this->fd[$name] = $fd;
            $this->fd_trace[$name] = $fd_trace;
        }
    }

    protected function begin_log(string $name) {
        $this->time[$name] = time();
        $this->log[$name] = '===== BEGIN : ' . $name . ' (' . date('Y-m-d H:i:s') . ' - ' . $this->time[$name] . ') =====' . PHP_EOL;
        $this->level[$name] = [
            'WORST' => self::NONE,
            self::INFO => 0,
            self::WARN => 0,
            self::ERR => 0,
            self::CRIT => 0,
            self::FATL => 0,
        ];
    }

    protected function not_end(string $name = null) {
        if($name === null || trim($name) === '') {
            $name = $this->active;
        }
        if($this->end[$name]) {
            throw new LoggerException("The log is end");
        }
    }

    protected function log_exist(string $name) {
        return $name !== null && in_array($name, $this->name);
    }

    protected function level_to_string($level) {
        switch($level) {
            case self::INFO:
                return '[INFO]';
                break;
            case self::WARN:
                return '[WARN]';
                break;
            case self::ERR:
                return '[ERR]';
                break;
            case self::CRIT:
                return '[CRIT]';
                break;
            case self::FATL:
                return '[FATL]';
                break;
            default:
                return '';
        }
    }

    protected function second_to_time($second) {
        $time;
        $min = (int) ($second / 60);
        if($min > 0) {
            $time = ($second - ($min * 60)) . 's';
            $hour = (int) ($min / 60);
            if($hour > 0) {
                $time = $hour . 'h ' . ($min - ($hour * 60)) . 'm ' . $time;
            } else {
                $time = $min . 'm ' . $time; 
            }
        } else {
            $time = $second . 's';
        }
        return $time;
    }

}

class LoggerException extends Exception {

    public function __construct($message = null, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

}