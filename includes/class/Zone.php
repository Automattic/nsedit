<?php

class Zone {
    public function __construct() {
        $this->id = '';
        $this->name = '';
        $this->kind = '';
        $this->url = '';
        $this->serial = '';
        $this->dnssec = '';
        $this->soa_edit = '';
        $this->soa_edit_api = '';
        $this->keyinfo = '';
        $this->account = '';
        $this->zone = false;
        $this->nameservers = Array();
        $this->rrsets = Array();
        $this->masters = Array();
    }

    public function parse($data) {
        $this->setid($data['id']);
        $this->setname($data['name']);
        $this->setkind($data['kind']);
        $this->setdnssec($data['dnssec']);
        $this->setaccount($data['account']);
        $this->setserial($data['serial']);
        $this->url = $data['url'];
        if (isset($data['soa_edit']))
            $this->setsoaedit($data['soa_edit']);
        if (isset($data['soa_edit_api']))
            $this->setsoaeditapi($data['soa_edit_api']);

        foreach ($data['masters'] as $master) {
            $this->addmaster($master);
        }

        if (isset($data['rrsets'])) {
            foreach ($data['rrsets'] as $rrset) {
                $toadd = new RRSet($rrset['name'], $rrset['type']);
                foreach ($rrset['comments'] as $comment) {
                    $toadd->addComment($comment['content'], $comment['account'], $comment['modified_at']);
                }
                foreach ($rrset['records'] as $record) {
                    $toadd->addRecord($record['content'], $record['disabled']);
                }
                $toadd->setttl($rrset['ttl']);
                array_push($this->rrsets, $toadd);
            }
        }
    }

    public function importdata($data) {
        $this->zone = $data;
    }

    public function setkeyinfo($info) {
        $this->keyinfo = $info;
    }

    public function addnameserver($nameserver) {
        foreach ($this->nameservers as $ns) {
            if ($nameserver == $ns) {
                throw new Exception("We already have this as a nameserver");
            }
        }
        array_push($this->nameservers, $nameserver);

    }

    public function setserial($serial) {
        $this->serial = $serial;
    }

    public function setsoaedit($soaedit) {
        $this->soa_edit = $soaedit;
    }

    public function setsoaeditapi($soaeditapi) {
        $this->soa_edit_api = $soaeditapi;
    }
    public function setname($name) {
        $this->name = $name;
    }

    public function setkind($kind) {
        $this->kind = $kind;
    }

    public function setaccount($account) {
        $this->account = $account;
    }

    public function setdnssec($dnssec) {
        $this->dnssec = $dnssec;
    }

    private function setid($id) {
        $this->id = $id;
    }

    public function addmaster($ip) {
        foreach ($this->masters as $master) {
            if ($ip == $master) {
                throw new Exception("We already have this as a master");
            }
        }
        array_push($this->masters, $ip);
    }

    public function erasemasters() {
        $this->masters = Array();
    }

    public function deleterrset($name, $type) {
        foreach ($this->rrsets as $rrset) {
            if ($rrset->name == $name and $rrset->type == $type) {
                $rrset->delete();
            }
        }
    }

    public function setrrsetttl($name, $type, $ttl) {
        foreach ($this->rrsets as $rrset) {
            if ($rrset->name == $name and $rrset->type == $type) {
                $rrset->setttl($ttl);
            }
        }   
    }

    public function addrrset($name, $type, $content, $ttl = 3600) {
        foreach ($this->rrsets as $rrset) {
            if ($rrset->name == $name and $rrset->type == $type) {
                throw new Exception("This rrset already exists.");
            }
        }
        $rrset = new RRSet($name, $type, $content, $ttl);
        array_push($this->rrsets, $rrset);
    }

    public function addrecord($name, $type, $content, $disabled = false, $ttl = 3600) {
        $found = FALSE;

        foreach ($this->rrsets as $rrset) {
            if ($rrset->name == $name and $rrset->type == $type) {
                $rrset->addRecord($content, $disabled);
                $found = TRUE;
            }
        }

        if (!$found) {
            throw new Exception("RRset does not exist for this record");
        }
    }

    public function rrsets2records() {
        $ret = Array();

        foreach ($this->rrsets as $rrset) {
            foreach ($rrset->export_records() as $record) {
                $record['name'] = $rrset->name;
                $record['ttl']  = $rrset->ttl;
                $record['type'] = $rrset->type;
                array_push($ret, $record);
            }
        }

        return $ret;
    }

    public function export() {
        $ret = Array();
        $ret['account'] = $this->account;
        $ret['nameservers'] = $this->nameservers;
        $ret['kind'] = $this->kind;
        $ret['name'] = $this->name;
        $ret['soa_edit'] = $this->soa_edit;
        $ret['soa_edit_api'] = $this->soa_edit_api;
        if ($this->zone) {
            $ret['zone'] = $this->zone;
            return $ret;
        }

        $ret['dnssec'] = $this->dnssec;
        if ($this->dnssec) {
            $ret['keyinfo'] = $this->keyinfo;
        }
        $ret['id'] = $this->id;
        $ret['masters'] = $this->masters;
        $ret['rrsets'] = $this->export_rrsets();
        $ret['serial'] = $this->serial;
        $ret['url'] = $this->url;
        
        return $ret;
    }

    private function export_rrsets() {
        $ret = Array();
        foreach ($this->rrsets as $rrset) {
            array_push($ret, $rrset->export());
        }

        return $ret;
    }
}

class RRSet {
    public function __construct($name = '', $type = '', $content = '', $ttl = 3600) {
        $this->name = $name;
        $this->type = $type;
        $this->ttl  = $ttl;
        $this->changetype = 'REPLACE';
        $this->records = Array();
        $this->comments = Array();

        if (isset($content) and $content != '') {
            $this->addRecord($content);
        }
    }

    public function delete() {
        $this->changetype = 'DELETE';
    }

    public function setttl($ttl) {
        $this->ttl = $ttl;
    }

    public function addRecord($content, $disabled = false) {
        foreach ($this->records as $record) {
            if ($record->content == $content) {
                throw Exception("Record already exists");
            }
        }

        $record = new Record($content, $disabled);
        array_push($this->records, $record);
    }

    public function addComment($content, $account, $modified_at = false) {
        $comment = new Comment($content, $account, $modified_at);
        array_push($this->comments, $comment);
    }

    public function export() {
        $ret = Array();
        $ret['comments'] = $this->export_comments();
        $ret['name'] = $this->name;
        $ret['records'] = $this->export_records();
        if ($this->changetype != 'DELETE') {
            $ret['ttl'] = $this->ttl;
        }
        $ret['type'] = $this->type;
        $ret['changetype'] = $this->changetype;
        return $ret;
    }

    public function export_records() {
        $ret = Array();
        foreach ($this->records as $record) {
            array_push($ret, $record->export());
        }

        return $ret;
    }

    public function export_comments() {
        $ret = Array();
        foreach ($this->comments as $comment) {
            array_push($ret, $comment->export());
        }
        
        return $ret;
    }

}

class Record {
    public function __construct($content, $disabled = FALSE) {
        $this->content = $content;
        $this->disabled = $disabled;
    }

    public function export() {
        $ret;

        $ret['content'] = $this->content;
        $ret['disabled'] = $this->disabled;

        return $ret;
    }
}

class Comment {
    public function __construct($content, $account, $modified_at) {
        $this->content = $content;
        $this->account = $account;
        $this->modified_at = $modified_at;
    }

    public function export() {
        $ret;

        $ret['content'] = $this->content;
        $ret['account'] = $this->account;
        $ret['modified_at'] = $this->modified_at;
    }
}

?>
