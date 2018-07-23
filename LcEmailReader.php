<?php

/* =============================================================================
 * LcEmailReader
 * Ver 1.3.0
 * @author Loquicom <contact@loquicom.fr>
 * =========================================================================== */

class LcEmailReader {

    /**
     * Objet boite email
     * @var flux IMAP
     */
    protected $mbox = null;

    /**
     * L'hote du flux
     * @var string
     */
    protected $host;

    /**
     * Le login
     * @var string 
     */
    protected $login;

    /**
     * Le mot de passe
     * @var string
     */
    protected $pass;

    /**
     * Garde la connexion au flux ouvert
     * @var boolean 
     */
    protected $keep;

    /**
     * Constructeur
     * Lance automatiquement la connexion si keep = true mais ne la coupe pas
     * Sinon lance et coupe la connexion a chaque appel de methode
     * @see imap_open
     * @param string $host - L'hote pour imap_open
     * @param string $login - Le login
     * @param string $pass - Le mot de passe
     * @param boolean $keep - Maintenir la connexion au flux IMAP [defaut = true]
     * @throws Exception - Impossible de se connecter au flux IMAP
     */
    public function __construct($host, $login, $pass, $keep = true) {
        $this->host = $host;
        $this->login = $login;
        $this->pass = $pass;
        $this->keep = $keep;
        if ($keep) {
            $this->mbox = imap_open($host, $login, $pass);
            if ($this->mbox === false) {
                $this->mbox = null;
                throw new Exception("Impossible de se connecter au flux");
            }
        }
    }

    /**
     * Ouvre le flux IMAP
     * @return boolean
     */
    public function openFlux() {
        //Si le flux n'est pas ouvert
        if ($this->mbox === null) {
            $this->mbox = imap_open($this->host, $this->login, $this->pass);
            //Si l'ouverture echoue
            if ($this->mbox === false) {
                $this->mbox = null;
                return false;
            }
        }
        return true;
    }

    /**
     * Ferme le flux IMAP
     * @return boolean
     */
    public function closeFlux() {
        //Si le flux est deja fermé
        if ($this->mbox === null) {
            return false;
        }
        //Si la fermeture fonctionne
        if (imap_close($this->mbox)) {
            $this->mbox = null;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Verifie le connexion au flux IMAP
     * @return boolean
     */
    private function checkFlux() {
        //Si le flux est censé resté ouvert
        if ($this->keep) {
            //Mais qu'il est fermé
            if ($this->mbox === null) {
                return false;
            }
            return true;
        }
        //Sinon on l'ouvre
        else {
            return $this->openFlux();
        }
    }

    /**
     * Genere une liste de tous les messges de la boite
     * @param boolean $moreInfo - Renvoyer plus d'information [defaut = false]
     * @return false|mixed - La liste des messges avec leurs informations
     */
    public function getListMessage($moreInfo = false) {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        //Recuperation de la liste des messages
        $mc = imap_check($this->mbox);
        $listMail = imap_fetch_overview($this->mbox, "1:{$mc->Nmsgs}", 0);
        $return = array();
        $i = 0;
        foreach ($listMail as $email) {
            $return[$i]['date'] = date('Y-m-d H:i:s', $email->udate);
            $return[$i]['from'] = $email->from;
            $return[$i]['to'] = $email->to;
            $return[$i]['subject'] = $email->subject;
            $return[$i]['msgNo'] = $email->msgno;
            if ($moreInfo) {
                $return[$i]['date_email'] = $email->date;
                $return[$i]['date_timestamp'] = $email->udate;
                $return[$i]['seen'] = (bool) $email->seen;
                $return[$i]['answered'] = (bool) $email->answered;
                $return[$i]['deleted'] = (bool) $email->deleted;
            }
            $i++;
        }
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
        //Retour
        return $return;
    }

    /**
     * Renvoie le contenue et les infos d'un message
     * @param int $msgNo - Le numero du message
     * @return false|mixed - Les infos du messges
     */
    public function getMessage($msgNo) {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        //Contenue du message
        $parts = imap_fetchstructure($this->mbox, $msgNo);
        if (isset($parts->parts)) {
            $parts = $parts->parts;
            $boundary = $parts[0]->parameters;
            //Recupération de la séparation du mail pour la retirer
            $i = 0;
            while ($i < count($boundary)) {
                if ($boundary[$i]->attribute == 'boundary') {
                    $tmp = count($boundary);
                    $boundary = $boundary[$i]->value;
                    $i = $tmp;
                }
                $i++;
            }
            if (!is_string($boundary)) {
                $boundary = '';
            }
            //Recuperation du nombre de piece jointes
            $attachments = 0;
            for ($i = 1; $i < count($parts); $i++) {
                $part = $parts[$i];
                if ($part->ifdisposition && strtolower($part->disposition) == "attachment" && $part->ifdparameters) {
                    $attachments++;
                }
            }
        } else {
            $boundary = '';
            $attachments = 0;
        }
        //Recuperation des infos du header
        $header = imap_headerinfo($this->mbox, $msgNo);
        //Recuperation et traitement du message
        $text = imap_fetchbody($this->mbox, $msgNo, 1);
        $text = str_replace('--' . $boundary, '', str_replace('--' . $boundary . '--', '', $text));
        //Preparation du retour
        $return = array(
            'from' => $header->fromaddress,
            'to' => array(),
            'replyto' => $header->reply_toaddress,
            'message' => $text,
            'attachments' => $attachments,
            'msgNo' => $msgNo
        );
        foreach ($header->to as $to) {
            $return['to'][] = $to->mailbox . '@' . $to->host;
        }
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
        //Retour
        return $return;
    }

    /**
     * Liste les pieces jointes d'un message
     * @param int $msgNo - Le numero du message
     * @return false|string[] - Informations sur les pieces jointes
     */
    public function getAttachmentsList($msgNo) {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        //Recuperation de la structure du message
        $structure = imap_fetchstructure($this->mbox, $msgNo);
        $return = array();
        //Si il y a des parties
        if (isset($structure->parts) && count($structure->parts)) {
            $parts = $structure->parts;
            //Place de la 1ere piece jointe
            $atcPos = 2;
            //Parcours des parties
            for ($i = 1; $i < count($parts); $i++) {
                $part = $parts[$i];
                //Si c'est une piece jointe
                if ($part->ifdisposition && strtolower($part->disposition) == "attachment" && $part->ifdparameters) {
                    //Recup le nom de la PJ
                    $findName = false;
                    $atcmName = "attachment";
                    if ($part->ifparameters == 1) {
                        $name = $this->getParameters($part->parameters, 'NAME');
                        if ($name !== false) {
                            $atcmName = imap_utf8($name);
                            $findName = true;
                        }
                    }
                    if ($part->ifdparameters == 1 && !$findName) {
                        $name = $this->getParameters($part->dparameters, 'FILENAME');
                        if ($name !== false) {
                            $atcmName = imap_utf8($name);
                            $findName = true;
                        }
                    }
                    //Recup le type
                    $type = $part->type;
                    $typeName = $this->getType($type);
                    if($part->ifsubtype == 1){
                        $typeName .= '/' . strtolower($part->subtype);
                    }
                    //Info PJ
                    $return[] = array('attachmentName' => $atcmName, 'type' => array('name' => $typeName, 'num' => $type), 'encoding' => array('name' => $this->getEncodeType($part->encoding), 'num' => $part->encoding), 'atcPos' => $atcPos);
                }
                $atcPos++;
            }
        }
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
        //Retour
        return $return;
    }

    /**
     * Retourne le contenue d'une piece jointe
     * @param int $msgNo - Le numero du message
     * @param int $atcPos - La position de la piece jointe
     * @return false|string[] - array('name' => ..., 'content' => ...)
     */
    public function getAttachment($msgNo, $atcPos, $decode = true, $fromType = false) {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        //Récupération du type et du nom de la piece jointe
        $structure = imap_fetchstructure($this->mbox, $msgNo);
        if (isset($structure->parts[$atcPos - 1])) {
            $part = $structure->parts[$atcPos - 1];
            //Recup type et encode de la pj
            $type = $part->type;
            $encode = $part->encoding;
            //Recup le nom de la PJ
            $findName = false;
            $atcmName = "attachment";
            if ($part->ifparameters == 1) {
                $name = $this->getParameters($part->parameters, 'NAME');
                if ($name !== false) {
                    $atcmName = imap_utf8($name);
                    $findName = true;
                }
            }
            if ($part->ifdparameters == 1 && !$findName) {
                $name = $this->getParameters($part->dparameters, 'FILENAME');
                if ($name !== false) {
                    $atcmName = imap_utf8($name);
                    $findName = true;
                }
            }
        } else {
            return false;
        }
        //Recupération de la piece jointe
        $atc = imap_fetchbody($this->mbox, $msgNo, $atcPos);
        //Information de la piece jointe
        $return = array('name' => $atcmName);
        if ($decode) {
            if ($fromType) {
                $atc = $this->decodeValueFromType($atc, $type);
            } else {
                $atc = $this->decodeValue($atc, $encode);
            }
        }
        $return['content'] = $atc;
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
        //Retour
        return $return;
    }

    /**
     * 
     * @param int $msgNo - Le numero du message
     * @param int $atcPos - La position de la piece jointes
     * @param string $path - Le chemin
     * @param string $name - Le nom des fichiers
     * @return boolean - Reussite ou echec
     */
    public function saveAttachment($msgNo, $atcPos, $path = './', $name = '', $allowedMimetype = null) {
        //Si besoins on verifie le mimetype
        if (is_array($allowedMimetype) && !empty($allowedMimetype)) {
            //Si le mimetype n'est pas autorisé
            if (!$this->getMimetypeAttachment($msgNo, $atcPos, $allowedMimetype)) {
                return false;
            }
        }
        //Recuperation du contenue de la piece jointe
        $atc = $this->getAttachment($msgNo, $atcPos);
        if ($atc === false) {
            return false;
        }
        //On definit le nom
        if ($name == '') {
            $name = $atc['name'];
        }
        //On verifie que le dossier de destination existe sinon on le créer
        $path .= ($path[strlen($path) - 1] != '/') ? '/' : '';
        if (!file_exists($path)) {
            mkdir($path, 077, true);
        }
        //Creation du fichier
        return (bool) file_put_contents($path . $name, $atc['content']);
    }

    /**
     * Sauvegarde toutes les pieces jointes d'un message
     * @param int $msgNo - Le numero du message
     * @param string $path - Le chemin
     * @param string $name - Le nom des fichiers
     * @return false|boolean[] - Réussite ou echec pour chaque pieces jointes
     */
    public function saveAllAttachment($msgNo, $path = './', $name = '', $allowedMimetype = null) {
        //Recupération de toutes les pieces jointes
        $actList = $this->getAttachmentsList($msgNo);
        if ($actList == false) {
            return false;
        }
        //Sauvegarde des pieces jointes
        $return = array();
        $useFileName = (trim($name) == '') ? true : false; //Indique si le nom utilisé est celui du fichier ou du parametre
        foreach ($actList as $atc) {
            $continue = true;
            //Si il y a une liste de type autorisé on verifie
            if (is_array($allowedMimetype) && !empty($allowedMimetype)) {
                //Si le mimetype n'est pas autorisé
                if (!$this->getMimetypeAttachment($msgNo, $atc['atcPos'], $allowedMimetype)) {
                    $continue = false;
                }
            }
            if ($continue) {
                //Récupération du nom et de l'extnsion
                $nameExp = explode('.', $atc['attachmentName']);
                if (count($nameExp) > 1) {
                    $ext = '.' . $nameExp[count($nameExp) - 1];
                    unset($nameExp[count($nameExp) - 1]);
                    $fileName = implode('.', $nameExp);
                } else {
                    $fileName = $nameExp[0];
                    $ext = '';
                }
                //On definit le nom avec celui du fichier ou celui en parametre
                if (!$useFileName) {
                    $fileName = $name;
                }
                //On cherche un suffixe jusqu'à avoir un nom unique
                $i = 1;
                $suffixe = '';
                while (file_exists($path . $name . $suffixe . $ext)) {
                    $suffixe = ' (' . $i++ . ')';
                }
                //Nom final du fichier
                $fileName = $fileName . $suffixe . $ext;
                //Sauvegarde du fichier
                $return[$fileName] = $this->saveAttachment($msgNo, $atc['atcPos'], $path, $fileName);
            }
        }
        //Retour
        return $return;
    }

    /**
     * Retourne le mimetype d'un fichier ou si il est present dans le talbeau allowedMimetype
     * @param int $msgNo - Le numero du message
     * @param int $atcPos - La position de la piece jointe
     * @param mixed $allowedMimetype - Extension de fichier autorisé
     * @return boolean|string - Extension autorisé ou l'extension
     */
    public function getMimetypeAttachment($msgNo, $atcPos, $allowedMimetype = null) {
        //Recup le contenue et l'extension de la pj
        $atc = $this->getAttachment($msgNo, $atcPos);
        $content = $atc['content'];
        $name = explode('.', $atc['name']);
        $fileExt = $name[count($name) - 1];
        //Analyse du mimetype et recup de l'extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }
        $mimetype = strtolower(finfo_buffer($finfo, $content)) . ";";
        $mimetype = substr($mimetype, 0, strpos($mimetype, ";"));
        //Si on trouve le mimetype text/x-c on vire le mot "double" du document et on reteste le mimetype (possible csv)
        if (trim($mimetype) == 'text/x-c') {
            $content = str_replace('double', '', $content);
            $mime = strtolower(finfo_buffer($finfo, $content)) . ";";
            $mime = substr($mime, 0, strpos($mime, ";"));
            if (in_array($mime, array("text/plain", "text/csv"))) { // Si c'est pas un csv on remet le mimetype d'origine
                $mimetype = $mime;
            }
        }
        $ext = $this->reverseMimeType($mimetype, $fileExt);
        //Si on doit verifier que le mimetype est autorisé
        if (is_array($allowedMimetype) && !empty($allowedMimetype)) {
            //On regarde si l'extension renvoyé est dans celle autorisé
            return in_array($ext, $allowedMimetype);
        }
        //Sinon retour de l'extension
        else {
            return $ext;
        }
    }

    /**
     * Tag un message avec le flag delete
     * @param int $msgNo - Le numero du message
     * @return boolean
     */
    public function deleteTag($msgNo) {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        imap_delete($this->mbox, $msgNo);
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
    }

    /**
     * Supprimme tous les messages avec le flag delete
     * @return boolean
     */
    public function deleteTaggedMessages() {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        imap_expunge($this->mbox);
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
        return true;
    }

    /**
     * Supprimme le messegage + tous les messages avec le flag delete
     * @param int $msgNo - Le numero du message
     * @return boolean
     */
    public function deleteMessage($msgNo) {
        if (!$this->keep) {
            return false;
        }
        $this->deleteTag($msgNo);
        $this->deleteTaggedMessages();
        return true;
    }

    /**
     * Supprime tous les messages
     */
    public function deleteAllMessages() {
        //Recuperation de tous les messages
        $list = $this->getListMessage();
        //On tag tous les messages pour les supprimer
        foreach ($list as $message) {
            $this->deleteTag($message['msgNo']);
        }
        //On supprime
        $this->deleteTaggedMessages();
    }

    /**
     * Retourne la référence de l'hôte sans la boite mail
     * @see imap_open
     * @return string - ex: {host:port\params}
     */
    public function getRef() {
        preg_match('#^{[^}]*}#', $this->host, $ref);
        return $ref[0];
    }

    /**
     * Retourne la liste des boites email associées a celle ouverte
     * @param string $pattern - Motif de recherche
     * @return mixed - Liste des boites email
     */
    public function getList($pattern = '*') {
        return imap_list($this->mbox, $this->getRef(), $pattern);
    }

    /**
     * Renvoie le flux IMAP
     * @return false|Mbox
     */
    public function getMbox() {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        return $this->mbox;
    }

    /**
     * Retourne la valeur d'une clef dans le tableaux d'objet (d)parameters
     * @param object $params - Le tableau d'objet
     * @param string $key - La clef
     * @return false|mixed - False si la clef n'existe pas ou la valeur
     */
    protected function getParameters($params, $key) {
        $key = strtoupper($key);
        foreach ($params as $param) {
            if (strtoupper($param->attribute) == $key) {
                return $param->value;
            }
        }
        return false;
    }

    /**
     * Retourne le type de contenue de la piece jointe à partir de l'entier
     * @param int $type - Entier indiquant le type
     * @return string
     */
    protected function getType($type) {
        switch ($type) {
            case TYPETEXT:
                return "text";
            case TYPEMULTIPART:
                return "multipart";
            case TYPEMESSAGE:
                return "message";
            case TYPEAPPLICATION:
                return "application";
            case TYPEAUDIO:
                return "audio";
            case TYPEIMAGE:
                return "image";
            case TYPEVIDEO:
                return "video";
            case TYPEMODEL:
                return "model";
            case TYPEOTHER:
                return "other";
            default:
                return "";
        }
    }

    /**
     * Retourne le type d'encodage de la piece jointe à partir de l'entier
     * @param int $encode - Entier indiquant le type
     * @return string
     */
    protected function getEncodeType($encode) {
        switch ($encode) {
            case ENC7BIT:
                return "7bit";
            case ENC8BIT:
                return "8bit";
            case ENCBINARY:
                return "binary";
            case ENCBASE64;
                return "base64";
            case ENCQUOTEDPRINTABLE:
                return "quoted-printable";
            case ENCOTHER:
                return "other";
            default :
                return "";
        }
    }

    /**
     * Décode le contenu d'une piece jointe en fonction de son type
     * /!\ Le type peut diffèrer de l'encodage veuillez plutot utiliser decodeValue
     * @param string $atc - La piece jointe
     * @param integer $type - Le type de contenu
     * @return piece jointe décodé
     */
    protected function decodeValueFromType($atc, $type) {
        switch ($type) {
            case TYPETEXT: //text
            case TYPEMULTIPART: //multipart
                $atc = imap_8bit($atc);
                break;
            case TYPEMESSAGE: //message
                $atc = imap_binary($atc);
                break;
            case TYPEAPPLICATION: //application
            case TYPEIMAGE: //image
            case TYPEVIDEO: //video
            case TYPEMODEL: //model
            case TYPEOTHER: //other
                $atc = imap_base64($atc);
                break;
            case TYPEAUDIO: //audio
                $atc = imap_qprint($atc);
                break;
        }
        return $atc;
    }

    /**
     * Décode le contenu d'une piece jointe
     * @param string $atc - La piece jointe
     * @param integer $encode - Le type d'encodage du contenu
     * @return piece jointe décodé
     */
    protected function decodeValue($atc, $encode) {
        switch ($encode) {
            case ENC7BIT:
            case ENC8BIT:
                $atc = imap_8bit($atc);
                break;
            case ENCBINARY:
                $atc = imap_binary($atc);
                break;
            case ENCBASE64:
            case ENCOTHER:
                $atc = imap_base64($atc);
                break;
            case ENCQUOTEDPRINTABLE:
                $atc = imap_qprint($atc);
                break;
        }
        return $atc;
    }

    /**
     * Permet de recupérer le libellé d'un format depuis le mimetype complet. Exemple : application/pdf -> 'pdf'
     * @param string $mimetype - Mimetype pour lequel on souhaite récupérer le format
     * @param string $fileExt [optional] - L'extension du fichier pour regarder en priorité si le mimetype correspond
     * (Permet d'eviter les probleme pour les mimetype qui correspondent à plusieurs extension comme text/plain)
     * @return string|false Retourne le format (pdf,doc, etc..) ou false si le format est inconnu
     */
    protected function reverseMimeType($mimetype, $fileExt = null) {
        //Tableau des mimes types
        $tabMime = array(
            'hqx' => array('application/mac-binhex40', 'application/mac-binhex', 'application/x-binhex40', 'application/x-mac-binhex40'),
            'cpt' => 'application/mac-compactpro',
            'csv' => array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain'),
            'bin' => array('application/macbinary', 'application/mac-binary', 'application/octet-stream', 'application/x-binary', 'application/x-macbinary'),
            'dms' => 'application/octet-stream',
            'lha' => 'application/octet-stream',
            'lzh' => 'application/octet-stream',
            'exe' => array('application/octet-stream', 'application/x-msdownload'),
            'class' => 'application/octet-stream',
            'psd' => array('application/x-photoshop', 'image/vnd.adobe.photoshop'),
            'so' => 'application/octet-stream',
            'sea' => 'application/octet-stream',
            'dll' => 'application/octet-stream',
            'oda' => 'application/oda',
            'pdf' => array('application/pdf', 'application/force-download', 'application/x-download', 'binary/octet-stream'),
            'ai' => array('application/pdf', 'application/postscript'),
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',
            'smi' => 'application/smil',
            'smil' => 'application/smil',
            'mif' => 'application/vnd.mif',
            'xls' => array('application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel', 'application/x-ms-excel', 'application/x-excel', 'application/x-dos_ms_excel', 'application/xls', 'application/x-xls', 'application/excel', 'application/download', 'application/vnd.ms-office', 'application/msword'),
            'ppt' => array('application/powerpoint', 'application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/msword'),
            'pptx' => array('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/x-zip', 'application/zip'),
            'wbxml' => 'application/wbxml',
            'wmlc' => 'application/wmlc',
            'dcr' => 'application/x-director',
            'dir' => 'application/x-director',
            'dxr' => 'application/x-director',
            'dvi' => 'application/x-dvi',
            'gtar' => 'application/x-gtar',
            'gz' => 'application/x-gzip',
            'gzip' => 'application/x-gzip',
            'php' => array('application/x-httpd-php', 'application/php', 'application/x-php', 'text/php', 'text/x-php', 'application/x-httpd-php-source'),
            'php4' => 'application/x-httpd-php',
            'php3' => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps' => 'application/x-httpd-php-source',
            'js' => array('application/x-javascript', 'text/plain'),
            'swf' => 'application/x-shockwave-flash',
            'sit' => 'application/x-stuffit',
            'tar' => 'application/x-tar',
            'tgz' => array('application/x-tar', 'application/x-gzip-compressed'),
            'z' => 'application/x-compress',
            'xhtml' => 'application/xhtml+xml',
            'xht' => 'application/xhtml+xml',
            'zip' => array('application/x-zip', 'application/zip', 'application/x-zip-compressed', 'application/s-compressed', 'multipart/x-zip'),
            'rar' => array('application/x-rar', 'application/rar', 'application/x-rar-compressed'),
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'mpga' => 'audio/mpeg',
            'mp2' => 'audio/mpeg',
            'mp3' => array('audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'),
            'aif' => array('audio/x-aiff', 'audio/aiff'),
            'aiff' => array('audio/x-aiff', 'audio/aiff'),
            'aifc' => 'audio/x-aiff',
            'ram' => 'audio/x-pn-realaudio',
            'rm' => 'audio/x-pn-realaudio',
            'rpm' => 'audio/x-pn-realaudio-plugin',
            'ra' => 'audio/x-realaudio',
            'rv' => 'video/vnd.rn-realvideo',
            'wav' => array('audio/x-wav', 'audio/wave', 'audio/wav'),
            'bmp' => array('image/bmp', 'image/x-bmp', 'image/x-bitmap', 'image/x-xbitmap', 'image/x-win-bitmap', 'image/x-windows-bmp', 'image/ms-bmp', 'image/x-ms-bmp', 'application/bmp', 'application/x-bmp', 'application/x-win-bitmap'),
            'gif' => 'image/gif',
            'jpeg' => array('image/jpeg', 'image/pjpeg'),
            'jpg' => array('image/jpeg', 'image/pjpeg'),
            'jpe' => array('image/jpeg', 'image/pjpeg'),
            'jp2' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'j2k' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpf' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpg2' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpx' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpm' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'mj2' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'mjp2' => array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'png' => array('image/png', 'image/x-png'),
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'css' => array('text/css', 'text/plain'),
            'html' => array('text/html', 'text/plain'),
            'htm' => array('text/html', 'text/plain'),
            'shtml' => array('text/html', 'text/plain'),
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'log' => array('text/plain', 'text/x-log'),
            'rtx' => 'text/richtext',
            'rtf' => 'text/rtf',
            'xml' => array('application/xml', 'text/xml', 'text/plain'),
            'xsl' => array('application/xml', 'text/xsl', 'text/xml'),
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'avi' => array('video/x-msvideo', 'video/msvideo', 'video/avi', 'application/x-troff-msvideo'),
            'movie' => 'video/x-sgi-movie',
            'doc' => array('application/msword', 'application/vnd.ms-office'),
            'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/msword', 'application/x-zip'),
            'dot' => array('application/msword', 'application/vnd.ms-office'),
            'dotx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/msword'),
            'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/vnd.ms-excel', 'application/msword', 'application/x-zip'),
            'word' => array('application/msword', 'application/octet-stream'),
            'xl' => 'application/excel',
            'eml' => 'message/rfc822',
            'json' => array('application/json', 'text/json'),
            'pem' => array('application/x-x509-user-cert', 'application/x-pem-file', 'application/octet-stream'),
            'p10' => array('application/x-pkcs10', 'application/pkcs10'),
            'p12' => 'application/x-pkcs12',
            'p7a' => 'application/x-pkcs7-signature',
            'p7c' => array('application/pkcs7-mime', 'application/x-pkcs7-mime'),
            'p7m' => array('application/pkcs7-mime', 'application/x-pkcs7-mime'),
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/pkcs7-signature',
            'crt' => array('application/x-x509-ca-cert', 'application/x-x509-user-cert', 'application/pkix-cert'),
            'crl' => array('application/pkix-crl', 'application/pkcs-crl'),
            'der' => 'application/x-x509-ca-cert',
            'kdb' => 'application/octet-stream',
            'pgp' => 'application/pgp',
            'gpg' => 'application/gpg-keys',
            'sst' => 'application/octet-stream',
            'csr' => 'application/octet-stream',
            'rsa' => 'application/x-pkcs7',
            'cer' => array('application/pkix-cert', 'application/x-x509-ca-cert'),
            '3g2' => 'video/3gpp2',
            '3gp' => array('video/3gp', 'video/3gpp'),
            'mp4' => 'video/mp4',
            'm4a' => 'audio/x-m4a',
            'f4v' => array('video/mp4', 'video/x-f4v'),
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
            'aac' => 'audio/x-acc',
            'm4u' => 'application/vnd.mpegurl',
            'm3u' => 'text/plain',
            'xspf' => 'application/xspf+xml',
            'vlc' => 'application/videolan',
            'wmv' => array('video/x-ms-wmv', 'video/x-ms-asf'),
            'au' => 'audio/x-au',
            'ac3' => 'audio/ac3',
            'flac' => 'audio/x-flac',
            'ogg' => array('audio/ogg', 'video/ogg', 'application/ogg'),
            'kmz' => array('application/vnd.google-earth.kmz', 'application/zip', 'application/x-zip'),
            'kml' => array('application/vnd.google-earth.kml+xml', 'application/xml', 'text/xml'),
            'ics' => 'text/calendar',
            'ical' => 'text/calendar',
            'zsh' => 'text/x-scriptzsh',
            '7zip' => array('application/x-compressed', 'application/x-zip-compressed', 'application/zip', 'multipart/x-zip'),
            'cdr' => array('application/cdr', 'application/coreldraw', 'application/x-cdr', 'application/x-coreldraw', 'image/cdr', 'image/x-cdr', 'zz-application/zz-winassoc-cdr'),
            'wma' => array('audio/x-ms-wma', 'video/x-ms-asf'),
            'jar' => array('application/java-archive', 'application/x-java-application', 'application/x-jar', 'application/x-compressed'),
            'svg' => array('image/svg+xml', 'application/xml', 'text/xml'),
            'vcf' => 'text/x-vcard',
            'srt' => array('text/srt', 'text/plain'),
            'vtt' => array('text/vtt', 'text/plain'),
            'ico' => array('image/x-icon', 'image/x-ico', 'image/vnd.microsoft.icon'),
            'odc' => 'application/vnd.oasis.opendocument.chart',
            'otc' => 'application/vnd.oasis.opendocument.chart-template',
            'odf' => 'application/vnd.oasis.opendocument.formula',
            'otf' => 'application/vnd.oasis.opendocument.formula-template',
            'odg' => 'application/vnd.oasis.opendocument.graphics',
            'otg' => 'application/vnd.oasis.opendocument.graphics-template',
            'odi' => 'application/vnd.oasis.opendocument.image',
            'oti' => 'application/vnd.oasis.opendocument.image-template',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'otp' => 'application/vnd.oasis.opendocument.presentation-template',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'odm' => 'application/vnd.oasis.opendocument.text-master',
            'ott' => 'application/vnd.oasis.opendocument.text-template',
            'oth' => 'application/vnd.oasis.opendocument.text-web'
        );
        //Si l'extension du fichier est indiquée
        if($fileExt !== null && trim($fileExt) != ''){
            //Regarde si le mimetype indiqué est dans la clef de l'extension du fichier
            $fileExt = strtolower($fileExt);
            if(isset($tabMime[$fileExt])){
                $mimelist = $tabMime[$fileExt];
                if(!is_array($mimelist)){
                    $mimelist = array($mimelist);
                }
                //Recherche
                if(in_array($mimetype, $mimelist)){
                    return $fileExt;
                }
            }
        }
        //Parcours le tableau de mimetype pour trouver l'extension
        foreach ($tabMime as $type => $val) {
            //Si c'est un string
            if (!is_array($val)) {
                //Regarde si ll sont identiques
                if ($mimetype === $val) {
                    return $type;
                }
            }
            //Regarde si dans le tableau
            else if (in_array($mimetype, $val)) {
                return $type;
            }
        }

        return false;
    }

}
