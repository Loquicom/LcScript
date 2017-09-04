<?php

/* =============================================================================
 * LcEmail by Loquicom
 * Ver 1.2
 * =========================================================================== */

class LcEmail {

    /**
     * Le format html ou text
     * @var string
     */
    private $format = 'html';

    /**
     * L'expediteur
     * @var string[]
     */
    private $from = null;

    /**
     * Les destinataires
     * @var string
     */
    private $to = null;

    /**
     * Le mail de reponse
     * @var string[]
     */
    private $reply = null;

    /**
     * Les copies
     * @var string|string[]
     */
    private $cc = null;

    /**
     * Les copies cach�es
     * @var string|string[]
     */
    private $bcc = null;

    /**
     * La priorit� du message entre 1 et 5
     * @var int
     */
    private $priority = 3;

    /**
     * L'objet du message
     * @var string
     */
    private $subject = '';

    /**
     * Le contenue du message
     * @var string
     */
    private $message = '';

    /**
     * Les pieces jointes
     * @var string[]
     */
    private $attach = null;

    /**
     * Le format du mail a envoyer
     * @param string $format - html ou text
     * @return $this
     */
    public function format($format) {
        if (in_array($format, array('html', 'text'))) {
            $this->format = $format;
        }
        return $this;
    }

    /**
     * L'expediteur de l'email
     * @param string $email - L'email de l'expediteur
     * @param string $name - Le nom de l'expediteur
     * @return $this
     */
    public function from($email, $name) {
        if ((bool) filter_var($email, FILTER_VALIDATE_EMAIL) && trim($name) != '') {
            $this->from['email'] = $email;
            $this->from['name'] = $name;
        }
        return $this;
    }

    /**
     * Le/Les destinataire(s) de l'email
     * @param string|string[] $email - L'email ou le tableau d'email des destinataires
     * @return $this
     */
    public function to($email) {
        //Si c'est une adresse email
        if (is_string($email) && (bool) filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->to = $email;
        }
        //Si c'est un tableau d'adresse email
        if (is_array($email) && !empty($email)) {
            //On parcours pour verifer que tous les emails sont correct
            $to = '';
            foreach ($email as $mail) {
                if ((bool) filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $to .= $mail . ', ';
                }
            }
            $to = rtrim(trim($to), ',');
            if ($to != '') {
                $this->to = $to;
            }
        }
        return $this;
    }

    /**
     * L'email de reponse
     * @param string $email - L'email de reponse
     * @param string $name - Le nom de l'email de reponse
     * @return $this
     */
    public function reply($email, $name) {
        if ((bool) filter_var($email, FILTER_VALIDATE_EMAIL) && trim($name) != '') {
            $this->reply['email'] = $email;
            $this->reply['name'] = $name;
        }
        return $this;
    }

    /**
     * L'/Les email(s) en copie
     * @param string|string[] $email - L'email ou le tableau d'email en copies
     * @return $this
     */
    public function cc($email) {
        //Si c'est une adresse email
        if (is_string($email) && (bool) filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->cc = $email;
        }
        //Si c'est un tableau d'adresse email
        if (is_array($email) && !empty($email)) {
            //On parcours pour verifer que tous les emails sont correct
            $cc = '';
            foreach ($email as $mail) {
                if ((bool) filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $cc .= $mail . ', ';
                }
            }
            $cc = rtrim(trim($cc), ',');
            if ($cc != '') {
                $this->cc = $cc;
            }
        }
        return $this;
    }

    /**
     * L'/Les email(s) en copie cach�e
     * @param string|string[] $email - L'email ou le tableau d'email en copies cach�es
     * @return $this
     */
    public function bcc($email) {
        //Si c'est une adresse email
        if (is_string($email) && (bool) filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bcc = $email;
        }
        //Si c'est un tableau d'adresse email
        if (is_array($email) && !empty($email)) {
            //On parcours pour verifer que tous les emails sont correct
            $bcc = '';
            foreach ($email as $mail) {
                if ((bool) filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                    $bcc .= $mail . ', ';
                }
            }
            $bcc = rtrim(trim($bcc), ',');
            if ($bcc != '') {
                $this->bcc = $bcc;
            }
        }
        return $this;
    }

    /**
     * Change la priorit� du mail
     * @param int|string $priority
     * @return $this
     */
    public function priority($priority) {
        if (in_array('' . $priority, array('1', '2', '3', '4', '5'))) {
            $this->priority = $priority;
        }
        return $this;
    }

    /**
     * L'objet de l'email
     * @param string $subject
     * @return $this
     */
    public function subject($subject) {
        if (is_string($subject)) {
            $this->subject = $subject;
        }
        return $this;
    }

    /**
     * Le message de l'email
     * @param string $message
     * @return $this
     */
    public function message($message) {
        if (is_string($message)) {
            $this->message = $message;
        }
        return $this;
    }

    /**
     * Ajoute une piece jointe
     * @param string $path - Le chemin d'acc�s au fichier
     * @param string $name - Le nom du fichier dans l'email
     * @return $this
     */
    public function addAttachment($path, $name = '') {
        if (file_exists($path)) {
            if (trim($name) == '') {
                $pathexplode = explode('/', explode('\\', $path)[count(explode('\\', $path)) - 1]);
                $filename = explode('.', $pathexplode[count($pathexplode) - 1]);
                $name = $filename[count($filename) - 2] . '.' . $filename[count($filename) - 1];
            }
            $this->attach[] = array('path' => $path, 'name' => $name);
        }
        return $this;
    }

    /**
     * Retire toutes les pieces jointes
     * @return $this
     */
    public function resetAttachment() {
        $this->attach = null;
        return $this;
    }

    /**
     * Parametrage complet de l'email
     * @param string $format - Le format html ou text
     * @param string[] $from - L'expediteur array('email' => 'test@mail.com', 'name' => 'test')
     * @param string|string[] $to - Le/Les destinataire(s)
     * @param string[] $reply - L'email de reponse array('email' => 'test@mail.com', 'name' => 'test')
     * @param string|string[] $cc - Le/Les email(s) en copie
     * @param string|string[] $bcc - Le/Les email(s) en copie
     * @param int $priority - La priorité entre 1 et 5 inclus
     * @param string $subject - L'objet
     * @param string $message - Lem essage
     * @param mixed $attach - Les pieces jointes array('path' => '...', 'name' => 'test') ou array(array('path1' => '...', 'name' => 'test1'), array(...))
     * @return $this
     */
    public function set($format = null, $from = null, $to = null, $reply = null, $cc = null, $bcc = null, $priority = null, $subject = null, $message = null, $attach = null) {
        if ($format !== null) {
            $this->format($format);
        }
        if ($from !== null && isset($from['email']) && isset($from['name'])) {
            $this->from($from['email'], $from['name']);
        }
        if ($to !== null) {
            $this->to($to);
        }
        if ($reply !== null && isset($reply['email']) && isset($reply['name'])) {
            $this->reply($reply['email'], $reply['name']);
        }
        if ($cc !== null) {
            $this->cc($cc);
        }
        if ($bcc !== null) {
            $this->bcc($bcc);
        }
        if ($priority !== null) {
            $this->priority($priority);
        }
        if ($subject !== null) {
            $this->subject($subject);
        }
        if ($message !== null) {
            $this->message($message);
        }
        if ($attach !== null && is_array($attach) && !empty($attach)) {
            if (isset($attach['path']) && file_exists($attach['path'])) {
                if (isset($attach['name'])) {
                    $this->addAttachment($attach['path'], $attach['name']);
                } else {
                    $this->addAttachment($attach['path']);
                }
            } else if (is_array($attach[0])) {
                foreach ($attach as $pj) {
                    if (isset($pj['path']) && file_exists($pj['path'])) {
                        if (isset($pj['name'])) {
                            $this->addAttachment($pj['path'], $pj['name']);
                        } else {
                            $this->addAttachment($pj['path']);
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Reinitialise l'email
     */
    public function clear() {
        $this->format = 'html';
        $this->from = null;
        $this->to = null;
        $this->reply = null;
        $this->cc = null;
        $this->bcc = null;
        $this->priority = 3;
        $this->subject = '';
        $this->message = '';
        $this->attach = null;
    }

    /**
     * Envoie l'email
     * @param boolean $clear - Reinitailis� les infos de l'email apr�s envoie
     * @return boolean
     */
    public function send($clear = true) {
        //Verification que tous est ok pour l'envoie
        if ($this->from === null) {
            return false;
        }

        //Passage de ligne
        $passage_ligne = "\r\n";
        //Separateur
        $separator = md5(rand());

        //Header de l'email
        $header = "From: " . $this->from['name'] . " <" . $this->from['email'] . ">" . $passage_ligne;
        if ($this->reply !== null && false) {
            $header .= "Reply-to: " . $this->reply['name'] . " <" . $this->reply['email'] . ">" . $passage_ligne;
        }
        if ($this->cc !== null) {
            $header .= "Cc: " . $this->cc . $passage_ligne;
        }
        if ($this->bcc !== null) {
            $header .= "Bcc: " . $this->bcc . $passage_ligne;
        }
        $header .= "Mime-Version: 1.0" . $passage_ligne;
        $header .= "X-Priority: " . $this->priority . $passage_ligne;
        $header .= "X-Mailer: LcEmail 1.2 " . $passage_ligne;
        $header .= "Date:" . date("D, d M Y h:s:i") . " +0200" . $passage_ligne;
        $header .= "Content-Transfer-Encoding: 7bit" . $passage_ligne;
        $header .= "Content-Type: multipart/mixed;boundary=" . $separator . $passage_ligne;

        //Message de l'email
        $message = "--" . $separator . $passage_ligne;
        if ($this->format == 'html') {
            //Email format html
            $message .= "Content-Type: text/html; charset=\"utf-8\"" . $passage_ligne;
            $message .= "Content-Transfer-Encoding: 8bit" . $passage_ligne;
            $message .= $this->message . $passage_ligne;
        } else {
            //Email format text
            $message .= "Content-Type: text/plain; charset=\"utf-8\"" . $passage_ligne;
            $message .= "Content-Transfer-Encoding: 8bit" . $passage_ligne;
            $message .= $this->message . $passage_ligne;
        }

        //Piece jointe de l'email
        if ($this->attach !== null) {
            foreach ($this->attach as $pj) {
                $content = chunk_split(base64_encode(file_get_contents($pj['path'])));
                $message .= "--" . $separator . $passage_ligne;
                $message .= "Content-Type: " . filetype($pj['path']) . "; name=\"" . $pj['name'] . "\"" . $passage_ligne;
                $message .= "Content-Transfer-Encoding: base64" . $passage_ligne;
                $message .= "Content-Disposition: attachment" . $passage_ligne;
                $message .= $content . $passage_ligne;
            }
        }

        //Fin du message
        $message .= "--" . $separator . "--" . $passage_ligne;

        $this->subject = 'Test';

        //Envoie du mail
        $result = mail($this->to, $this->subject, $message, $header);

        //Clear si demand�
        if ($clear) {
            $this->clear();
        }

        //Retour
        return $result;
    }

}
