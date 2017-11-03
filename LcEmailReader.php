<?php

/* =============================================================================
 * LcEmailReader
 * Ver 1.2
 * @author Loquicom <contact@loquicom.fr>
 * =========================================================================== */

class EmailReader {

    /**
     * Objet boite email
     * @var flux IMAP
     */
    private $mbox = null;

    /**
     * L'hote du flux
     * @var string
     */
    private $host;

    /**
     * Le login
     * @var string 
     */
    private $login;

    /**
     * Le mot de passe
     * @var string
     */
    private $pass;

    /**
     * Garde la connexion au flux ouvert
     * @var boolean 
     */
    private $keep;

    /**
     * Constructeur
     * Lance automatiquement la connexion si keep = true mais ne la coupe pas
     * Sinon lance et coupe la connexion a chaque appel de methode
     * @see imap_open
     * @param string $host - L'hote pour imap_open
     * @param string $login - Le login
     * @param string $pass - Le mot de passe
     * @param boolean $keep - Maintenir la connexion au flux IMAP [defaut = false]
     * @throws Exception - Impossible de se connecter au flux IMAP
     */
    public function __construct($host, $login, $pass, $keep = false) {
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
                    $atcmName = imap_utf8($part->dparameters[0]->value);
                    $return[] = array('attachmentName' => $atcmName, 'type' => $this->getType($part->type), 'atcPos' => $atcPos);
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
    public function getAttachment($msgNo, $atcPos) {
        //Verifie que le flux est ouvert
        if ($this->checkFlux() === false) {
            return false;
        }
        //Récupération du type et du nom de la piece jointe
        $structure = imap_fetchstructure($this->mbox, $msgNo);
        if (isset($structure->parts[$atcPos - 1])) {
            $type = $structure->parts[$atcPos - 1]->type;
            if ($structure->parts[$atcPos - 1]->ifdparameters) {
                $name = imap_utf8($structure->parts[$atcPos - 1]->dparameters[0]->value);
            } else {
                $name = 'attachment';
            }
        } else {
            return false;
        }
        //Recupération de la piece jointe
        $atc = imap_fetchbody($this->mbox, $msgNo, $atcPos);
        //Decodage de la piece jointe
        $return = array('name' => $name, 'content' => $this->decodeValue($atc, $type));
        //Ferme le flux si besoins
        if (!$this->keep) {
            $this->closeFlux();
        }
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
        //Recup le contenue de la pj
        $content = $this->getAttachment($msgNo, $atcPos)['content'];
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
        var_dump($mimetype);
        $ext = $this->reverseMimeType($mimetype);
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
    }

    /**
     * Supprimme le messegage + tous les messages avec le flag delete
     * @param int $msgNo - Le numero du message
     */
    public function deleteMessage($msgNo) {
        $this->deleteTag($msgNo);
        $this->deleteTaggedMessages();
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
     * Retourne le type de contenue de la piece jointe à partir de l'entier
     * @param int $type - Entier indiquant le type
     * @return string
     */
    private function getType($type) {
        switch ($type) {
            case 0:
                return "text";
            case 1:
                return "multipart";
            case 2:
                return "message";
            case 3:
                return "application";
            case 4:
                return "audio";
            case 5:
                return "image";
            case 6:
                return "video";
            case 7:
                return "other";
            default:
                return "";
        }
    }

    /**
     * Décode le contenu d'une piece jointe
     * @param string $atc - La piece jointe
     * @param integer $type - Le type de contenu
     * @return piece jointe décodé
     */
    private function decodeValue($atc, $type) {
        switch ($type) {
            case 0: //text
            case 1: //multipart
                $atc = imap_8bit($atc);
                break;
            case 2: //message
                $atc = imap_binary($atc);
                break;
            case 3: //application
            case 5: //image
            case 6: //video
            case 7: //other
                $atc = imap_base64($atc);
                break;
            case 4: //audio
                $atc = imap_qprint($atc);
                break;
        }
        return $atc;
    }

    /**
     * Permet de recupérer le libellé d'un format depuis le mimetype complet. Exemple : application/pdf -> 'pdf'
     * @param string $mimetype Mimetype pour lequel on souhaite récupérer le format
     * @return string|false Retourne le format (pdf,doc, etc..) ou false si le format est inconnu
     */
    private function reverseMimeType($mimetype) {

        $tabMime = array(
            'wmf' => array("application/x-httpd-php ", "text/x-c", "text/x-c++", "magnus-internal/shellcgi", "application/x-msdownload", "application/exe", "application/x-exe", "application/dos-exe", "vms/exe", "application/x-winexe", "application/x-dosexec", "application/msdos-windows", "application/x-msdos-program"),
            'dwg' => array("application/x-httpd-php ", "text/x-c", "text/x-c++", "magnus-internal/shellcgi", "application/x-msdownload", "application/exe", "application/x-exe", "application/dos-exe", "vms/exe", "application/x-winexe", "application/x-dosexec", "application/msdos-windows", "application/x-msdos-program"),
            'exe' => array("application/octet-stream", "application/x-httpd-php ", "text/x-c", "text/x-c++", "magnus-internal/shellcgi", "application/x-msdownload", "application/exe", "application/x-exe", "application/dos-exe", "vms/exe", "application/x-winexe", "application/x-dosexec", "application/msdos-windows", "application/x-msdos-program"),
            'zip' => array("application/zip", "application/x-zip", "application/x-zip-compressed", "application/x-compress", "application/x-compressed", "multipart/x-zip", "application/x-rar-compressed"),
            'pdf' => array("application/pdf"),
            'doc' => array("application/msword"),
            'docx' => array("application/vnd.openxmlformats-officedocument.wordprocessingml.document", "application/vnd.openxmlformats-officedocument.wordprocessingml"),
            'xls' => array("application/vnd.ms-excel"),
            'xlsx' => array("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "application/vnd.openxmlformats-officedocument.spreadsheetml"),
            'ppt' => array("application/vnd.ms-powerpoint"),
            'pptx' => array("application/vnd.openxmlformats-officedocument.presentationml.presentation", "application/vnd.openxmlformats-officedocument.presentationml"),
            'odt' => array("application/vnd.oasis.opendocument.text"),
            'odg' => array("application/vnd.oasis.opendocument.graphics"),
            'ods' => array("application/vnd.oasis.opendocument.spreadsheet"),
            'odp' => array("application/vnd.oasis.opendocument.presentation"),
            'rtf' => array("application/rtf"),
            'xml' => array("text/plain", "text/xml", "application/xml"),
            'gif' => array("image/gif"),
            'jpg' => array("image/jpeg", "image/pjpeg"),
            'png' => array("image/png", "image/x-png"),
            'csv' => array("text/plain", "text/csv"),
            'php' => array("text/php", "text/x-php", "application/php", "application/x-php")
        );

        foreach ($tabMime as $type => $val) {
            if (in_array($mimetype, $val)) {
                return $type;
            }
        }

        return false;
    }

}