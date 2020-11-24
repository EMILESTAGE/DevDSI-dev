<?php
/**
 * Created by PhpStorm.
 * User: yttyyw
 * Date: 01/01/2018
 * Time: 22:47
 */

class Cga_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getSolde($btq, $mois, $annee, $jour = null)
    {
        setDB(DB_READ);
        if ($jour == null)
            return $this->db->query("
                SELECT montant
                FROM soldecga
                WHERE boutique = ? AND mois = ? AND annee = ?
                ORDER BY jour DESC LIMIT 1
            ", array($btq, $mois, $annee))->result();

        return $this->db->query("
                SELECT montant
                FROM soldecga
                WHERE boutique = ? AND jour= ?
            ", array($btq, $jour))->result();
    }

    public function getRefillRaa($secteur,$mois,$annee){
        setDB(DB_READ);
        return $this->db->query("
            SELECT (montant), DATE(t.close_date) jour
            FROM rechargecga r
            INNER JOIN boutiques b ON b.id = r.boutique
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE b.type = 6 AND t.state=1 AND b.secteur=? and r.mois=? AND r.annee=?
        ",array($secteur,$mois,$annee))->result();
    }
    public function getRequestToRaa($respo=null,$mois=null,$annee=null){
        setDB(DB_READ);
        $m=$y=$s="";
        if($mois!=null)
            $m = " AND MONTH(t.close_date) = $mois";
        if($annee!=null)
            $y = " AND YEAR(t.close_date) = $annee";
        if($respo!=null)
            $s = " AND t.next_user = $respo";

        setDB(DB_READ);
        return $this->db->query("   
            SELECT (montant), DATE(t.close_date) jour
            FROM requettes_pdv r
            INNER JOIN boutiques b ON b.id = r.boutique
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE t.state=1 $m $y $s
        ")->result();

//         var_dump($this->db->last_query());die;
    }

    public function getCgaToPayToDate($boutique,$date){

        $p=" AND MONTH(m.date)=MONTH(CURRENT_DATE) AND YEAR(m.date)=YEAR(CURRENT_DATE)";

        setDB(DB_READ);
        return $this->db->query("
            SELECT m.id as mId, m.montantCGA as dette, m.date_paiement,b.nom
            FROM memos m
            LEFT JOIN boutiques b ON b.id = m.boutique
            WHERE m.objet in ? AND m.boutique = ? and m.date_paiement <= ? AND b.type NOT IN ?  AND m.state >=1 and m.state!=2 $p
            GROUP BY m.id
            ORDER BY  m.id ASC LIMIT 1
        ",array(array(APPROVISIONNEMENT_CGA,AVANCE_COMMISSION_CGA),$boutique,$date,array(PDV_AG,PDV_RAA,PDV_RFVI,PDV_RVAD)))->result();

    }
    public function getCurrentCgaToPay($boutique){
        setDB(DB_READ);
        $p=" AND MONTH(m.date)=MONTH(CURRENT_DATE) AND YEAR(m.date)=YEAR(CURRENT_DATE)";
        return $this->db->query("
            SELECT m.id as mId, m.montantCGA as dette, m.date_paiement,b.nom
            FROM memos m
            LEFT JOIN boutiques b ON b.id = m.boutique
            WHERE m.objet in ? AND m.boutique = ?  AND b.type NOT IN ?  AND m.state >=1 and m.state!=2 $p
            GROUP BY m.id
            ORDER BY  m.id ASC LIMIT 1
        ",array(array(APPROVISIONNEMENT_CGA,AVANCE_COMMISSION_CGA),$boutique,array(PDV_AG,PDV_RAA,PDV_RFVI,PDV_RVAD)))->result();

    }

    public function getCommission($btq, $mois, $annee)
    {
        setDB(DB_READ);
        return $this->db->query("
            SELECT montant
            FROM commissions
            WHERE boutique = ? AND mois = ? AND annee = ?
            ORDER BY jour DESC LIMIT 1
        ", array($btq, $mois, $annee))->result();
    }

    public function uniqueRef($ref){
        setDB(DB_READ);
        $query=$this->db->query(
            "SELECT *
            FROM rechargecga r
            LEFT JOIN tickets t ON t.id = r.ticket
            WHERE r.n_versement=?
            AND (r.valide!=-1 OR t.state!=-1) ",
            array($ref)
        )->result();
//        $query=$this->db->where('n_versement', $ref)->where('valide !=', -1)->from('rechargecga')->select('id')->get()->result();
        return empty($query);
    }
    public function uniqueRefCga($ref){
        setDB(DB_READ);
        $query=$this->db->query(
            "SELECT *
            FROM rechargecga r
            WHERE r.ref_cga=?
             ",
            array($ref)
        )->result();
//        $query=$this->db->where('n_versement', $ref)->where('valide !=', -1)->from('rechargecga')->select('id')->get()->result();
        return empty($query);
    }

    public function lastCgaRequest()
    {
        setDB(DB_READ);
        $query = $this->db->query("select * from rechargecga where id=(select max(id) from rechargecga) and id>=(select count(id) from rechargecga)")->result();
        return !empty($query) ? $query[0] : array();
    }

    public function newRefillRequest($shop, $paymode, $paytype, $file = null, $ref, $amount, $ctrlId, $ticket, $type = STD,$valide=0,$api_payment=0)
    {
        setDB(DB_WRITE);
        if ($paytype == BANQUE or $paytype==COMPENSATION_CGA_MATERIEL) {
            $this->db->query("insert into rechargecga(boutique, moyen_paiement, fichier, n_versement, montant, cfinancier,ticket,type,jour,mois,annee) values (?,?,?,?,?, ?, ?, ?, ?, ?,?)",
                array($shop, $paymode, $file, $ref, $amount, $ctrlId, $ticket, $type,moment()->format('Y-m-d'),moment()->format('m'),moment()->format('Y')));
        } else if ($paytype == MOPAY) {
            $this->db->query("insert into rechargecga(boutique, moyen_paiement, n_versement, montant, cfinancier,ticket,type,jour,mois,annee,valide,api_payement) values (?,?,?,?, ?, ?,?, ?, ?,?,?,?)",
                array($shop, $paymode, $ref, $amount, $ctrlId, $ticket, $type,moment()->format('Y-m-d'),moment()->format('m'),moment()->format('Y'),$valide,$api_payment));
        }
        return $this->db->insert_id();
    }

    /**
     * @param $ctrl ;le controlleur
     * @return null
     * Requettes a valider
     */
    public function getRequestToValidate($ctrl = null, $dateS = "", $btq = 0, $data=false, $params=array("typeRequest"=>'('.STD.')'))
    {
        setDB(DB_READ);

        $typeRequest = "";
        if(isset($params["typeRequest"]))
            $typeRequest = "AND rechargecga.type IN (".$params["typeRequest"].")";
        else
            $typeRequest = "AND rechargecga.type IN (".STD.")";

        if ($ctrl != null) {
            $d = "";
            $b = "";
            if ($dateS != "") {
                $d = " AND rechargecga.date_recharge='$dateS'";
            }
            //var_dump($d);die();
            if ($btq != 0)
                $b = " AND rechargecga.boutique=$btq";
            //var_dump($b,$btq);die();

            $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type, type_payment.nom as tNom,
                        tickets.id as tkId, tickets.num as tkNum, tickets.num, tickets.init_user, tickets.next_user,
                        tickets.next_role, tickets.commentaire, tickets.state as tState, tickets.state,tickets.open_date,tickets.close_date,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE rechargecga.cfinancier=? AND boutiques.controleur_boutique = u.id AND rechargecga.type = ?
                          $d $b
                         order by rechargecga.id desc",
                array($ctrl))->result();

        }
        else {
            if((isAssistantDfin($data["next_roles"]) or isOperatriceTicketsFinanciers($data["next_roles"]) or isOperatriceGestionCaisse($data["next_roles"])) and is_array($data)){
                $nr = $data["next_roles"];
                $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type, u.nom as cnom, u.prenom as cpnom,u.tel,
                        tickets.id as tkId, tickets.num as tkNum, tickets.num , tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,type_payment.nom as tNom,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where rechargecga.valide=0 and tickets.next_roles LIKE CONCAT('%\"', ?, '\"%') $typeRequest
                         order by rechargecga.id desc",
                    array($data["next_roles"]))->result();
            }
            else{
                $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type, u.nom as cnom, u.prenom as cpnom,u.tel,
                        tickets.id as tkId, tickets.num as tkNum, tickets.num , tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,type_payment.nom as tNom,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where rechargecga.valide=0 and tickets.next_role=? $typeRequest
                         order by rechargecga.id desc",
                    array(ROLE_DIR_FINANCIER))->result();
            }

        }


        //var_dump($this->db->last_query());die();

        return !empty($query) ? $query : array();
    }

    /**
     * @param
     * @return null
     * Demandes déjà validée
     */
    public function getRequestValidated($ctrl = null, $debut=" ",$fin=" ", $btq = 0, $params=array("typeRequest"=>'('.STD.')'))
    {
        setDB(DB_WRITE);
        $d = "";
        $b = "";

        if ($debut != " ") {
            $d = " AND rechargecga.date_recharge>='$debut' AND rechargecga.date_recharge<='$fin'";
            if(isController())  $d = " AND tickets.open_date>='$debut' AND tickets.close_date<='$fin'";
        }
        if ($btq != 0)
            $b = " AND rechargecga.boutique=$btq";
        //var_dump($b);die();

        $typeRequest = "";
        if(isset($params["typeRequest"]))
            $typeRequest = "AND rechargecga.type IN (".$params["typeRequest"].")";
        else
            $typeRequest = "AND rechargecga.type IN (".STD.")";

        setDB(DB_READ);
        $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where rechargecga.valide=1 $typeRequest $d $b
                         order by rechargecga.id desc")->result();
        // if(isController()) var_dump($debut,$fin);die;


        return !empty($query) ? $query : array();
    }


    public function getRequestValidatedCDT($ctrl = null, $debut=" ",$fin=" ", $btq = 0)
    {
        $d = "";
        $b = "";

        if ($debut != " ") {
            $d = " AND DATE(rechargecga.date_recharge)>='$debut' AND DATE(rechargecga.date_recharge)<='$fin'";
        }
        if ($btq != 0)
            $b = " AND rechargecga.boutique=$btq";
        //var_dump($b);die();

        setDB(DB_READ);
        $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where rechargecga.valide=1 AND rechargecga.type=? $d $b
                         order by rechargecga.id desc", array(CDT))->result();

        return !empty($query) ? $query : array();
    }



    public function getRequestValidatedAtime($ctrl = null, $debut = "",$fin="", $btq = 0)
    {
        $d = "";
        $b = "";

        if ($debut != "") {
            $d = " AND DATE(rechargecga.date_recharge)>='$debut' AND  DATE(rechargecga.date_recharge)<='$fin'";
        }
        if ($btq != 0)
            $b = " AND rechargecga.boutique=$btq";
        //var_dump($b);die();

        setDB(DB_READ);
        $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where rechargecga.valide=1 AND rechargecga.type=? $d $b
                         order by rechargecga.id desc", array(STD))->result();

        return !empty($query) ? $query : array();
    }

    /**
     * @return null
     * LES REQUETES pretes pour crédit
     */
    public function getReadyRequests($params=array("typeRequest"=>'('.STD.')'))
    {
        setDB(DB_READ);
        $typeRequest = "";
        if(isset($params["typeRequest"]))
            $typeRequest = "AND r.type IN (".$params["typeRequest"].")";
        else
            $typeRequest = "AND rechargecga.type IN (".STD.")";

        $query = $this->db->query("
      select r.*, r.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
       moyen_payment.label, type_payment.id as type,u.nom as cnom, u.role as cRole, u.prenom as cpnom,u.tel,
       tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
       tickets.next_role, tickets.commentaire, tickets.state,
       pr.status as prStatus
         from rechargecga r
         left join tickets on r.ticket = tickets.id
         left join boutiques on r.boutique = boutiques.id
         LEFT JOIN users u ON u.id = r.cfinancier
         left join moyen_payment on r.moyen_paiement = moyen_payment.id
         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
         LEFT JOIN payment_references pr ON pr.id=r.reference_id 
         where r.valide=1 and r.recharge=0 and tickets.state=0 $typeRequest
         order by r.id desc")->result();
        return !empty($query) ? $query : array();
    }

    /**
     * LES requettes déjà créditées
     * @param null $date
     * @param null $cmanager
     * @return null
     */
    public function getCreditedRequests($cmanager = null, $debut = "",$fin=" ", $btq = 0, $params=array("typeRequest"=>'('.STD.')'))
    {
        //var_dump($debut,$fin);die;
        $query = "";
        $d = "";
        $b = "";

        if ($debut != "") {
            $d = " AND rechargecga.date_recharge>='$debut' AND rechargecga.date_recharge<='$fin'";
        }
        if ($btq != 0)
            $b = " AND rechargecga.boutique=$btq";

        setDB(DB_READ);
        if (!empty($cmanager)) {
            $typeRequest = "";
            if(isset($params["typeRequest"]))
                $typeRequest = "AND rechargecga.type IN (".$params["typeRequest"].")";
            else
                $typeRequest = "AND rechargecga.type IN (".STD.")";

            $query = $this->db->query("select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                    moyen_payment.label, type_payment.id as type, users.nom as cnom, users.prenom as cpnom,users.tel,
                    tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                    tickets.next_role, tickets.commentaire, tickets.state,
                    memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         LEFT JOIN users ON rechargecga.cfinancier = users.id
                         where recharge=1 $d $b $typeRequest
                        order by id desc")->result();
        }
        else{
            $typeRequest = "";
            if(isset($params["typeRequest"]))
                $typeRequest = "AND rechargecga.type IN (".$params["typeRequest"].")";
            else
                $typeRequest = "AND rechargecga.type IN (".STD.")";

            $query = $this->db->query("select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                    moyen_payment.label, type_payment.id as type, users.nom as cnom, users.prenom as cpnom,users.tel,
                    tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                    tickets.next_role, tickets.commentaire, tickets.state,
                    memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         LEFT JOIN users ON rechargecga.cfinancier = users.id
                         where recharge=1 $d and tickets.state=? $typeRequest
                        order by id desc", array(FERME))->result();
        }

        return !empty($query) ? $query : array();

    }

    public function getRequests($shop, $dateS = null,$init=null)
    {
        $periodeString = generatePeriodeCriteria2(session_data('periode'),"tickets",false,false,false,'open_date');
        $i="";

        if($init)
            $i=" AND soldeCGA = 1";

        setDB(DB_READ);
        if (empty($dateS))
            //Les dmeandes d'un DA précis
            $query = $this->db->query("
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                    moyen_payment.label, type_payment.id as type, tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state, tickets.open_date, tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement,
                    tickets.commentaire AS tkMotif,
                    pr.status as prStatus,rechargecga.api_payement as api_payement
                     from rechargecga
                     INNER JOIN tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                     LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     LEFT JOIN payment_references pr ON rechargecga.reference_id = pr.id
                     where rechargecga.boutique=? $periodeString $i
                     order by rechargecga.id desc",
                array(intval($shop)))->result();
        else
            //Demandes d'une boutique traité par un controleur précis
            $query = $this->db->query("
                    select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                     moyen_payment.label, type_payment.id as type,
                     tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     left join tickets on rechargecga.ticket = tickets.id
                     INNER JOIN memos ON memos.id = rechargecga.memo
                     INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where rechargecga.boutique=? and rechargecga.date_recharge=? $periodeString $i
                     order by rechargecga.id desc",
                array(intval($shop), $dateS))->result();


        return !empty($query) ? $query : array();
    }


    /*public function validateRequest($request, $amount, $reference='')
    {
        if (!empty($reference))
            $this->db->query("update rechargecga set montant=?, n_versement=?,  valide=1, users=? where id=?", array($amount, $reference, json_encode(array(ROLE_DIR_FINANCIER)), $request));
        else
            $this->db->query("update rechargecga set montant=?,  valide=1, users=? where id=?", array($amount, json_encode(array(ROLE_DIR_FINANCIER)), $request));
    }*/

    public function validateRequest($request, $amount, $reference = '', $role = false, $date = null)
    {
        setDB(DB_WRITE);
        if ($role) {
            if (!empty($reference))
                $this->db->query("update rechargecga set date_recharge=?, montant=?, n_versement=?,  valide=1, recharge = 1, users=? where id=?", array($date, $amount, $reference, $role, $request));
            else
                $this->db->query("update rechargecga set date_recharge=?,montant=?,  valide=1, recharge = 1, users=? where id=?", array($date, $amount, $role, $request));
        } else {
            if (!empty($reference))
                $this->db->query("update rechargecga set montant=?, n_versement=?,  valide=1, users=? where id=?", array($amount, $reference, json_encode(array(ROLE_DIR_FINANCIER)), $request));
            else
                $this->db->query("update rechargecga set montant=?,  valide=1, users=? where id=?", array($amount, json_encode(array(ROLE_DIR_FINANCIER)), $request));
        }
    }

    public function getRequest($id)
    {
        setDB(DB_READ);
        $query = $this->db->query('select rechargecga.*,
                              payment.id as pId, payment.nom,
                              moyen_payment.label,
                              tickets.id as tId, tickets.num,tickets.state tState,tickets.close_date tClose,
                              b.nom as bNom, b.id AS bId,b.secteur, b.tel AS bTel,
                              u.email as uEmail, u.nom uNom, u.prenom uPrenom
                              from rechargecga
                              LEFT JOIN boutiques b ON b.id = rechargecga.boutique
                              LEFT JOIN users u ON u.boutique = b.id
                              LEFT JOIN moyen_payment ON rechargecga.moyen_paiement = moyen_payment.id
                              LEFT JOIN type_payment payment ON moyen_payment.type = payment.id
                              left join tickets on rechargecga.ticket=tickets.id
                              where rechargecga.id=?', array(intval($id)))->result();
        return !empty($query) ? $query[0] : array();
    }

    public function credit($shop, $amount)
    {
        setDB(DB_WRITE);
        $this->db->query("update soldecga set montant=montant+? where boutique=? and mois=? and annee=? and jour=?",
            array(intval($amount), $shop, intval(date('m')), intval(date('Y')), date('Y-m-d')));
    }

    public function addCgaBalance($shop, $amount)
    {
        setDB(DB_WRITE);
        $this->db->query("insert into soldecga (boutique, montant, mois, annee, jour) values(?, ?, ?, ?, ?)",
            array(intval($shop), intval($amount), intval(date('m')), intval(date('Y')), moment()->format('Y-m-d')));
    }

    public function setRefilled($request, $rType, $cmanager, $ref = null)
    {
        setDB(DB_WRITE);
        if (($rType == STD) or ($rType == REX)) {
            $this->db->query("update rechargecga set recharge=1, cmanager=?, date_recharge=?, users=?, ref_cga=? where id=? ", array(intval($cmanager), moment()->format('Y-m-d H:i:s'), json_encode(array(ROLE_DIR_FINANCIER, ROLE_CREDIT_MANAGER)), $ref, intval($request)));
        } else {
            $this->db->query("update rechargecga set recharge=1, cmanager=?, date_recharge=?, users=?, ref_cga=? where id=?", array(intval($cmanager), moment()->format('Y-m-d H:i:s'), json_encode(array(ROLE_DIR_FINANCIER, ROLE_CREDIT_MANAGER, ROLE_SUPERVISEUR, ROLE_CONTROLEUR)), $ref, intval($request)));
        }

    }

    public function getCgaBalance($shop, $date)
    {
        setDB(DB_READ);
        $query = $this->db->query("select * from soldecga where boutique=? and jour=?", array(intval($shop), $date))->result();
        return !empty($query) ? $query[0] :array();
    }

    public function getRechargeOfYesterday($boutique, $day)
    {
        setDB(DB_READ);
        $query = $this->db->query('select sum(montant) as montant
                         from rechargecga
                         where recharge=1 and boutique=? AND  date_recharge=? order by id desc',
            array($boutique, $day))->result();
        return $query;

    }

    public function getCurrentRefills_old($boutique)
    {
        setDB(DB_READ);
        $query = $this->db->query("select * from rechargecga where boutique=? and recharge=0 and valide not in (1,-1)", array($boutique))->result();
        return !empty($query) ? $query :array();
    }

    public function getCurrentRefills__($boutique)
    {
        setDB(DB_READ);
        $query = $this->db->query("select * from rechargecga where boutique=? and recharge=0", array($boutique))->result();
        return !empty($query) ? $query :array();
    }

    public function getCurrentRefills($boutique)
    {
        setDB(DB_READ);
        $query = $this->db->query("select *
              from rechargecga  r
              INNER JOIN tickets t ON t.id=r.ticket
              where r.boutique=? and (t.state=0 or t.state=2) and r.type=?", array($boutique, CDT))->result();
        return !empty($query) ? $query :array();
    }

    public function rejectRequest($id)
    {
        setDB(DB_WRITE);
        $this->db->query('update rechargecga set valide=? where id=?', array(-1, $id));
    }

    public function getCredit($idBtq = false)
    {
        setDB(DB_READ);
        return $this->db->query("
        SELECT SUM(rechargecga.montant) AS montant
        FROM rechargecga
        WHERE rechargecga.boutique = ?
        AND rechargecga.type = ?
        AND rechargecga.paye = ?
        AND rechargecga.recharge = ?
        ", array($idBtq, CDT, NON_PAYE, 1))->result();
    }

    public function getDemande($idBtq = false, $cdt = false)
    {
        setDB(DB_READ);
        return $this->db->query("
        SELECT *
        FROM rechargecga
        WHERE rechargecga.boutique = ?
        AND rechargecga.type = ?
        AND rechargecga.recharge = ?
        ", array($idBtq, $cdt, 0))->result();
    }

    public function getOneRequestToValidate($idRcga = false)
    {
        setDB(DB_READ);
        if ($idRcga) {
            return $this->db->query("
                        select rechargecga.*, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.num AS tkNum, tk.id AS tkId,
                        memos.state AS mState, memos.code AS mCode,memos.mensualite
                         from rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE rechargecga.id=? AND boutiques.controleur_boutique = u.id
                         ", array($idRcga))->result();
        }
        return false;
    }


    public function creditCga($roleId = false,$user_id=false,$user_role=null)
    {
        setDB(DB_READ);
        if ($roleId) {
            if ($roleId == ROLE_CONTROLEUR) {
                $query= $this->db->query("
                        select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType, boutiques.id AS btqId,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.num AS tkNum, tk.id AS tkId,tk.open_date,tk.close_date,tk.commentaire,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId, memos.date_paiement,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel, tk.commentaire AS tkMotif
                         from rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         INNER JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE tk.next_role=? AND tk.next_user = ? AND rechargecga.type = ? AND tk.state != ? AND tk.state != ?
                         ORDER BY rechargecga.id DESC
                         ", array($roleId, $user_id?$user_id:session_data('id'), CDT, FERME, BLOCKE))->result();
                return $query;
            }
            else {
                if(isAssistantDfin($user_role) or isOperatriceTicketsFinanciers($user_role) or isOperatriceGestionCaisse($user_role) or isAssistantCreditManager($user_role)){
                    $query= $this->db->query("
                        select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType, boutiques.id AS btqId,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.num AS tkNum, tk.id AS tkId,tk.open_date,tk.close_date,tk.commentaire,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId, memos.date_paiement,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel
                         from rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         INNER JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE tk.next_roles LIKE CONCAT('%\"', ?, '\"%') AND rechargecga.type = ? AND tk.state != ? AND tk.state != ?
                         ORDER BY rechargecga.id DESC
                         ", array($roleId, CDT, FERME, BLOCKE))->result();
                    return $query;
                }
                else{
                    $query= $this->db->query("
                        select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType, boutiques.id AS btqId,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.num AS tkNum, tk.id AS tkId,tk.open_date,tk.close_date,tk.commentaire,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId, memos.date_paiement,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel
                         from rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         INNER JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE tk.next_role=? AND rechargecga.type = ? AND tk.state != ? AND tk.state != ?
                         ORDER BY rechargecga.id DESC
                         ", array($roleId, CDT, FERME, BLOCKE))->result();
                    return $query;
                }
            }

        } else {
            $query= $this->db->query("
                        select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType, boutiques.id AS btqId,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.num AS tkNum, tk.id AS tkId,tk.open_date,tk.close_date,tk.commentaire,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId, memos.date_paiement,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel
                         from rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         INNER JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE rechargecga.type = ? AND tk.state != ? AND tk.state != ?
                         ORDER BY rechargecga.id DESC
                         ", array(CDT, FERME, BLOCKE))->result();
                return $query;
        }

    }


    public function creditCgaValidate($roleId = false, $type = CDT,$debut=null,$fin=null, $btq = 0)
    {
        setDB(DB_READ);
        if ($roleId) {
            $d=$periodeString='';
            /*if($debut!=null){
                $d=" AND  tk.close_date <= '$fin' AND tk.close_date >= '$debut' ";
            }*/
            $periodeString = generatePeriodeCriteria2(session_data("periode"),"tk",false,false,false,'close_date');

            if(isController() ||isSuperviseur()){
                //$d=" AND  tk.open_date <= '$fin' AND tk.open_date >= '$debut' ";
                $periodeString = generatePeriodeCriteria2(session_data("periode"),"tk",false,false,false,'close_date');
                //var_dump($debut,$fin);die;
            }
            $b = "";
            if ($btq != 0)
                $b = " AND rechargecga.boutique=$btq";

            if (isController()) {
                //$d=" AND  tk.open_date <= '$fin' AND tk.open_date >= '$debut' ";
                $periodeString = generatePeriodeCriteria2(session_data("periode"),"tk",false,false,false,'close_date');

                return $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.state, tk.num, tk.num AS tkNum, tk.id AS tkId, tk.next_user, tk.next_role,tk.open_date,tk.close_date,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel, tk.commentaire AS tkMotif
                         FROM rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         INNER JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE boutiques.controleur_boutique = ? $b
                         AND (rechargecga.users LIKE CONCAT('%', ?, '%') or rechargecga.users LIKE CONCAT('%', ?, '%')) AND rechargecga.type = ? $periodeString ORDER BY rechargecga.id DESC
                         ", array(session_data('id'), $roleId, $roleId, $type))->result();
            } else {
                $dfin = ROLE_DIR_FINANCIER;
                $condAsdfin = "(rechargecga.users LIKE CONCAT('%', $dfin, '%') or tk.next_roles LIKE CONCAT('%', $dfin, '%'))";
                if(isAssistantDfin() or isOperatriceTicketsFinanciers() or isOperatriceGestionCaisse())
                    $condAsdfin = "(rechargecga.users LIKE CONCAT('%', $dfin, '%') or tk.next_roles LIKE CONCAT('%', $dfin, '%'))";

                return $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.state, tk.num, tk.num AS tkNum, tk.id AS tkId, tk.next_user, tk.next_role,tk.open_date,tk.close_date,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId, tk.commentaire AS tkMotif
                         FROM rechargecga
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         INNER JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE $condAsdfin AND rechargecga.type = ? $b $periodeString ORDER BY rechargecga.id DESC
                         ", array($type))->result();
            }
        }
        return false;
    }

    public function creditCgaReject($roleId = false)
    {
        $periodeString = generatePeriodeCriteria2(session_data('periode'),"tk",false,false,false,'open_date');

        $c = "";
        if (isController())
            $c = " boutiques.controleur_boutique = u.id AND ";

        $visible = "";
        if(isDirecteurFinancier())
            $visible = " AND tk.next_role = ".session_data('roleId')." OR tk.next_roles LIKE CONCAT('%\"', ".ROLE_ASSISTANT_DFIN.", '\"%')";
        if(isAssistantDfin() or isOperatriceTicketsFinanciers() or isOperatriceGestionCaisse())
            $visible = " AND tk.next_role = ".ROLE_DIR_FINANCIER." OR tk.next_roles LIKE CONCAT('%\"', ".session_data('roleId').", '\"%')";


        setDB(DB_READ);
        if ($roleId) {
            return $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.state, tk.num, tk.num AS tkNum, tk.id AS tkId, tk.next_user, tk.next_role,tk.open_date,tk.close_date,
                        memos.state AS mState, memos.code AS mCode, memos.id AS mId, memos.users AS mUsers,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel, tk.commentaire AS tkMotif
                         from rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE $c ((rechargecga.type = ?) OR (rechargecga.type=?) OR (rechargecga.type=?)) AND tk.state = " . BLOCKE . " $visible $periodeString
                         ORDER BY rechargecga.id DESC
                         ", array(CDT, STD, REX))->result();
        }
        return false;
    }

    public function monthlyCgaCredit($month=null,$year=null,$typeRefill=array(STD,CDT),$btqType=null){
        $m= " AND MONTH(t.close_date) = MONTH(CURRENT_DATE)";
        $y =" AND YEAR(t.close_date) = YEAR(CURRENT_DATE)";
        $t = "";

        if($month!=null)
            $m = " AND MONTH(t.close_date) = $month";
        if($year!=null)
            $y = " AND YEAR(t.close_date) = $year";
        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";


        setDB(DB_READ);
        return $this->db->query("
            SELECT SUM(r.montant) as montant, r.type as rType, DATE(t.close_date) as jour
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE r.recharge=1 and r.type in ? $m $y $t
            GROUP BY DATE(t.close_date)
            ORDER BY t.close_date ASC

        ",array($typeRefill))->result();

    }

    public function monthlyRexCredit($month=null,$year=null,$typeRefill=array(REX),$btqType=null){
        $m= " AND MONTH(t.close_date) = MONTH(CURRENT_DATE)";
        $y =" AND YEAR(t.close_date) = YEAR(CURRENT_DATE)";
        $t = "";

        if($month!=null)
            $m = " AND MONTH(t.close_date) = $month";
        if($year!=null)
            $y = " AND YEAR(t.close_date) = $year";
        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";


        setDB(DB_READ);
        return $this->db->query("
            SELECT SUM(r.montant_crediter) as montant, r.type as rType, DATE(t.close_date) as jour
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE r.recharge=1 and r.type in ? $m $y $t
            GROUP BY DATE(t.close_date)
            ORDER BY t.close_date ASC

        ",array($typeRefill))->result();

    }
    
    public function getGlobalPreRefillCga($month=null,$year=null,$btqType=null){
        $m= " AND MONTH(t.open_date) = MONTH(CURRENT_DATE)";
        $y =" AND YEAR(t.open_date) = YEAR(CURRENT_DATE)";
        $t = "";

        if($month!=null)
            $m = " AND MONTH(t.open_date) = $month";
        if($year!=null)
            $y = " AND YEAR(t.open_date) = $year";
        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";



        setDB(DB_READ);
        return $this->db->query("
            SELECT SUM(r.montant) as montant, DATE(t.open_date) as jour
            FROM rechargecga r
            INNER JOIN moyen_payment mp ON mp.id = r.moyen_paiement
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE t.state=1 and r.type in ? and mp.type!=? $m $y $t
            GROUP BY DATE(t.open_date)
            ORDER BY t.open_date ASC

        ",array(array(PRE_FIN,STD),COMPENSATION_CGA_MATERIEL))->result();

    }

    public function getGlobalPreRefillRex($month=null,$year=null,$btqType=null){

        $m= " AND MONTH(t.open_date) = MONTH(CURRENT_DATE)";
        $y =" AND YEAR(t.open_date) = YEAR(CURRENT_DATE)";
        $t = "";

        if($month!=null)
            $m = " AND MONTH(t.open_date) = $month";
        if($year!=null)
            $y = " AND YEAR(t.open_date) = $year";
        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";



        setDB(DB_READ);
        return $this->db->query("
            SELECT SUM(r.montant) as montant, DATE(t.open_date) as jour
            FROM rechargecga r
            INNER JOIN moyen_payment mp ON mp.id = r.moyen_paiement
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE t.state=1 and r.type in ? and mp.type!=? $m $y $t
            GROUP BY DATE(t.open_date)
            ORDER BY t.open_date ASC

        ",array(array(REX),COMPENSATION_CGA_MATERIEL))->result();

    }
    public function OneDayRefillCga($date=null,$btqType=null){

        $t = "";


        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";

        setDB(DB_READ);
        return $this->db->query("
            SELECT (r.montant) as montant, DATE(t.open_date) as jour,mp.label,b.nom as nBtq,r.n_versement ref,mp.id as mid,r.type rType
            FROM rechargecga r
            INNER JOIN moyen_payment mp ON mp.id = r.moyen_paiement
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE t.state=1 and r.type in ? and mp.type not in ? and DATE(t.open_date)=? $t

            ORDER BY t.open_date ASC

        ",array(array(STD,PRE_FIN),array(COMPENSATION_CGA_MATERIEL),$date))->result();

    }
    public function OneDayRefillRex($date=null,$btqType=null){

        $t = "";


        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";

        setDB(DB_READ);
        return $this->db->query("
            SELECT (r.montant) as montant, DATE(t.open_date) as jour,mp.label,b.nom as nBtq,r.n_versement ref,mp.id as mid,r.type rType
            FROM rechargecga r
            INNER JOIN moyen_payment mp ON mp.id = r.moyen_paiement
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE t.state=1 and r.type in ? and mp.type not in ? and DATE(t.open_date)=? $t

            ORDER BY t.open_date ASC

        ",array(array(REX),array(COMPENSATION_CGA_MATERIEL),$date))->result();

    }
    
    public function RefillCgaByPerionde($periode=array(),$idMoyenPayment=false, $btqType=null){

        if($periode["fin"] != null){
            $periodeString = " AND (DATE(t.open_date) >= '".$periode["debut"]."') and (DATE(t.open_date) <= '".$periode["fin"]."')";
        }
        else{
            $periodeString = " AND (DATE(t.open_date) = '".$periode["debut"]."')";
        }
        $t = "";


        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";

        setDB(DB_READ);
        return $this->db->query("
            SELECT SUM(r.montant) as montant
            FROM rechargecga r
            INNER JOIN moyen_payment mp ON mp.id = r.moyen_paiement
            INNER JOIN tickets t ON t.id = r.ticket
            INNER JOIN boutiques b ON b.id = r.boutique
            WHERE r.recharge=1 and r.type in (0,2) AND mp.id = $idMoyenPayment and mp.type not in ? $periodeString $t

            ORDER BY t.open_date ASC

        ",array(array(COMPENSATION_CGA_MATERIEL)))->result();

    }

    public function getGlobalPostRefillCga($month=null,$year=null,$btqType=null){

        $m= " AND MONTH(r.date) = MONTH(CURRENT_DATE)";
        $y =" AND YEAR(r.date) = YEAR(CURRENT_DATE)";
        $t = "";

        if($month!=null)
            $m = " AND MONTH(r.date) = $month";
        if($year!=null)
            $y = " AND YEAR(r.date) = $year";
        if($btqType!=null)
            $t = " AND b.type in (".implode(',', array_map('intval', $btqType)).")";

        setDB(DB_READ);
        return $this->db->query("
            SELECT (r.montant), DATE(r.date) as jour

      FROM remboursement_dette r
       INNER JOIN memos m ON m.id = r.memo
       INNER JOIN objets_memo o ON o.id = m.objet
       INNER JOIN boutiques b ON b.id = m.boutique
       WHERE r.state = 1 AND m.objet = ? $t $m $y
        ",array(APPROVISIONNEMENT_CGA))->result();

    }

    public function detailCgaCredit($jour,$monthly=false,$boutique=-1,$params=array()){

        $b="";

        if(!$monthly)
            $d = "AND DATE_FORMAT(tickets.close_date,'%Y-%m-%d') = '$jour'";
        else{
            $d = "AND MONTH(tickets.close_date) = ".moment($jour)->format('m')." 
                    AND YEAR(tickets.close_date) = ".moment($jour)->format('Y');
        }

        if($boutique!=-1){
            $b = " AND rechargecga.boutique=$boutique";
        }

        setDB(DB_READ);
        $query = $this->db->query("select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                    moyen_payment.label, type_payment.id as type, users.nom as cnom, users.prenom as cpnom,users.tel,
                    tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                    tickets.next_role, tickets.commentaire, tickets.state,
                    moyen_payment.label,
                    memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         left join boutiques on rechargecga.boutique = boutiques.id
                         left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         LEFT JOIN users ON rechargecga.cfinancier = users.id
                         where recharge=1 $d  and  rechargecga.type in ? $b
                        order by tickets.close_date ASC", array($params["arryType"]))->result();


        return $query;
    }
    
    public function cgaByShop(){

        $periodeString = generatePeriodeCriteria2(session_data('periode'),"tickets",false,false,false,'open_date');

        setDB(DB_READ);
        $query = $this->db->query("select count(rechargecga.id) as nbr, SUM(rechargecga.montant) as montant,boutiques.id,boutiques.nom,boutiques.numdist,boutiques.type
                         from rechargecga
                         left join tickets on rechargecga.ticket = tickets.id
                         left join boutiques on rechargecga.boutique = boutiques.id
                         where rechargecga.recharge=1  and  rechargecga.type in ? $periodeString
                         GROUP BY boutiques.id
                         ORDER by montant DESC 
                        ", array(array(STD,CDT)))->result();


        return $query;
    }

    public function getIdTicket($idMemo = false)
    {
        setDB(DB_READ);
        if ($idMemo) {
            return $this->db->select('id, ticket')->from('rechargecga')->where(array('memo' => $idMemo))->get()->result();
        }
        return false;
    }

    public function getRequestsc($shop, $dateS = null)
    {
        setDB(DB_READ);
        if (empty($dateS))
            //LEs dmeandes d'un DA précis
            $query = $this->db->query('
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                    moyen_payment.label, type_payment.id as type, tk.id as tkId, tk.num, tk.init_user, tk.next_user,
                    tk.next_role, tk.commentaire, tk.state,
                    memos.state AS mState, memos.code AS mCode, tk.commentaire AS tkMotif
                     from rechargecga
                     INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     left join boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where rechargecga.boutique=?
                     order by rechargecga.id desc',
                array(intval($shop)))->result();
        else
            //Demandes d'une boutique traité par un controleur précis
            $query = $this->db->query('
                    select rechargecga.*, boutiques.nom, boutiques.numdist, boutiques.type AS btqType,
                     moyen_payment.label, type_payment.id as type
                     from rechargecga
                     left join boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where rechargecga.boutique=? and rechargecga.date_recharge=?
                     order by rechargecga.id desc',
                array(intval($shop), $dateS))->result();
        return !empty($query) ? $query :array();
    }

    /**
     * @param bool $data
     * @param bool $btq
     * @param bool $mode pour savoir s'il faut couper ou diminuer
     * @return bool
     */
    public function updateSoldeCga($data = false, $btq = false, $mode = false, $date = false)
    {
        setDB(DB_WRITE);
        $date = ($date) ? $date : date("Y-m-d");


        if ($mode===true)
        {//mode==true on retire du solde cga les montant envoyé
            //var_dump($mode); die;
            if ($data and $btq) {
                $d = $this->db->query("select * from soldecga WHERE boutique = ? AND jour = ? limit 1", array($btq, $date))->result();//requette du jour
                //var_dump($d); die;
                if (empty($d)) {//pas de solde pour la journée
                    $d = $this->db->query("select * from soldecga WHERE boutique = ? ORDER BY jour DESC", array($btq))->result();//recupérer le dernier solde
                    if(empty($d))//pas de dernier solde cad jamais crédité!
                    { //var_dump($d); die;
                        $data['montant'] = -intval($data['montant']);
                        return $this->db->insert('soldecga', $data);
                    }
                    else{//on a trouvé le dernier solde
                        $data['jour'] = $date;//mise a jour date
                        //var_dump($data, $d); die;
                        $data['montant'] = intval($d[0]->montant) - intval($data['montant']);//montant = last solde + montant requette
                        return $this->db->insert('soldecga', $data);
                    }
                    /*$data['montant'] = -intval($data['montant']);
                    return $this->db->insert('soldecga', $data);*/
                } elseif (count($d) == 1) {
                    //var_dump($d); die;
                    $data['montant'] = intval($d[0]->montant) - intval($data['montant']);
                    $this->db->update('soldecga', $data, array('id' => $d[0]->id));
                    return $this->db->affected_rows();
                } else return false;
            }
        }
        elseif($mode===false) {//mode==false on augmente du solde cga les montant envoyé
            if ($data and $btq) {
                $d = $this->db->query("select * from soldecga WHERE boutique = ? AND jour = ?", array($btq, $date))->result();
                //var_dump($d); die;
                if (empty($d)) {
                    $d = $this->db->query("select * from soldecga WHERE boutique = ? ORDER BY jour DESC", array($btq))->result();//recupérer le dernier solde
                    if(empty($d))//pas de dernier solde cad jamais crédité!
                    {
                        $data['montant'] = intval($data['montant']);
                        return $this->db->insert('soldecga', $data);
                    }
                    else{//on a trouvé le dernier solde
                        $data['jour'] = $date;//mise a jour date
                        $data['montant'] = intval($d[0]->montant) + intval($data['montant']);//montant = last solde + montant requette
                        return $this->db->insert('soldecga', $data);
                    }
//                    return $this->db->insert('soldecga', $data);
                } elseif (count($d) == 1) {
                    $data['montant'] = intval($d[0]->montant) + intval($data['montant']);
                    $this->db->update('soldecga', $data, array('id' => $d[0]->id));
                    return $this->db->affected_rows();
                } else return false;
            }
        }
        elseif($mode===-1){

            $date=$data['jour'];
            if ($data and $btq) {
                $d = $this->db->query("select * from soldecga WHERE boutique = ? AND jour = ?", array($btq, $date))->result();
                //var_dump($d); die;
                if (empty($d)) {
                    {//on a trouvé le dernier solde
                        $data['jour'] = $date;//mise a jour date
                        $data['montant'] = intval($data['montant']);//montant =  montant requette
                        return $this->db->insert('soldecga', $data);
                    }
//                    return $this->db->insert('soldecga', $data);
                } elseif (count($d) == 1) {
                    $data['montant'] =  intval($data['montant']);
                    $this->db->update('soldecga', $data, array('id' => $d[0]->id));
                    return $this->db->affected_rows();
                } else return false;
            }
        }

        return false;
    }




    public function getAvgMontantBtq($idBtq = false)
    {
        setDB(DB_READ);
        if ($idBtq) {
            $avg = 0;
            $tabs = $this->db->query("
            SELECT rechargecga.montant
            FROM rechargecga
            WHERE boutique = ?
              ORDER BY rechargecga.id DESC
            LIMIT 0, 3
            ", array($idBtq))->result();

            if ($tabs) {
                $sum = 0;
                foreach ($tabs as $tab) {
                    $sum += $tab->montant;
                }
                $avg = $sum / 3;
            }
            return $avg;
        }
        return null;
    }

    public function prepToTrait($data)
    {

        $b = $r = $ty = "";
        if (isset($data['boutique']))
            $b = " AND r.boutique=" . $data['boutique'];

        if (isset($data['role'])){
            $r = " AND t.next_role=" . $data['role'];
            if (isAssistantDfin() or isOperatriceTicketsFinanciers() or isOperatriceGestionCaisse())
                $r = " AND t.next_roles LIKE CONCAT('%\"', ".$data['role'].", '\"%')";
            if (isAssistantCreditManager())
                $r = " AND t.next_roles LIKE CONCAT('%\"', ".$data['role'].", '\"%')";
        }

        if(($data['type'] == STD)){
            if (isCreditManager()|| isAssistantCreditManager())
                 $ty = "and r.type in (".STD.") and r.valide=1";
            else
                $ty = "and r.type in (".STD.")";
        }
        else if (($data['type'] == CDT))
            $ty = "and r.type in (".STD.")";
        else if (($data['type'] == REX))
            $ty = "and r.type in (".REX.")";

        setDB(DB_READ);
        $query = $this->db->query("
            SELECT COUNT(r.id) as nbr
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE t.state=? $ty $b $r
            ",
            array($data['state']))->result();


        return empty($query) ? 0 : $query[0]->nbr;
    }

    public function postToTrait($data)
    {
        $b = $r = $ty = "";
        if (isset($data['boutique']))
            $b = " AND r.boutique=" . $data['boutique'];

        if (isset($data['role'])) {
            $r = " AND t.next_role=" . $data['role'];
            if (isController())
                $r .= " AND t.next_user=" . $data['user'];
            if (isAssistantDfin() or isAssistantCreditManager() or isOperatriceTicketsFinanciers() or isOperatriceGestionCaisse())
                $r = " AND t.next_roles LIKE CONCAT('%\"', ".$data['role'].", '\"%')";
        }

        if($data['type'] == STD)
            $ty = "AND r.type in (".STD.",".REX.")";
        else
            $ty = "AND r.type in (".CDT.")";


        setDB(DB_READ);
        $query = $this->db->query("
            SELECT COUNT(r.id) as nbr
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE t.state=? $ty $b $r
            ",
            array($data['state']))->result();


        return empty($query) ? 0 : $query[0]->nbr;
    }

    public function prepTraited($data)
    {

        $b = $r = "";
        if (isset($data['boutique']))
            $b = " AND r.boutique=" . $data['boutique'] . " AND t.state=" . $data['state'];

        if (isset($data['role']))
            $r = " AND r.users LIKE CONCAT('%', " . $data['role'] . ", '%')";

        if (isController())
            $r .= " AND r.cfinancier = " . session_data('id');

        setDB(DB_READ);
        $query = $this->db->query("
            SELECT COUNT(r.id) as nbr
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE r.type=? $b $r
            ",
            array($data['type']))->result();

        return empty($query) ? 0 : $query[0]->nbr;
    }

    public function postTraited($data)
    {

        $b = $r = "";
        if (isset($data['boutique']))
            $b = " AND r.boutique=" . $data['boutique'];

        if (isset($data['role']))
            $r = " AND r.users LIKE CONCAT('%', " . $data['role'] . ", '%')";

        if (isController())
            $r .= " AND r.cfinancier = " . session_data('id');

        setDB(DB_READ);
        $query = $this->db->query("
            SELECT COUNT(r.id) as nbr
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE t.state=? AND r.type=? $b $r
            ",
            array($data['state'], $data['type']))->result();

        return empty($query) ? 0 : $query[0]->nbr;
    }


    public function prepRejected($data)
    {

        $b = $r = "";
        if (isset($data['boutique']))
            $b = " AND r.boutique=" . $data['boutique'];

        if (isset($data['role']))
            $r = " AND t.next_role=" . $data['role'];

        setDB(DB_READ);
        $query = $this->db->query("
            SELECT COUNT(r.id) as nbr
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE r.type=? AND t.state= ? $b $r
            ",
            array($data['type'], $data['state']))->result();


        return empty($query) ? 0 : $query[0]->nbr;
    }

    public function postRejected($data)
    {

        $b = $r = "";
        if (isset($data['boutique']))
            $b = " AND r.boutique=" . $data['boutique'];

        if (isset($data['role']))
            $r = " AND t.next_role=" . $data['role'];

        if (isController())
            $r .= " AND r.cfinancier = " . session_data('id');
        setDB(DB_READ);
        $query = $this->db->query("
            SELECT COUNT(r.id) as nbr
            FROM rechargecga r
            INNER JOIN tickets t ON t.id = r.ticket
            WHERE r.type=? AND t.state= ? $b $r
            ",
            array($data['type'], $data['state']))->result();


        return empty($query) ? 0 : $query[0]->nbr;
    }

    public function getRequestvalidatedByRole($role = false,$id=null,$btq=0)
    {

        $periodeString = generatePeriodeCriteria2(session_data('periode'),"tickets",false,false,false,'open_date');
        setDB(DB_READ);
        $bSql = "";
        if($btq != 0)
            $bSql = "AND rechargecga.boutique = $btq";

        if ($role) {
            $i="";
            if($id!=null)
                $i = " AND tickets.next_user=$id";
            $in = '1';
            if ($role == ROLE_RESPO_AA) $in = "aa_secteur.user=$id";
            return $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state, tickets.commentaire AS tkMotif,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN aa_secteur ON aa_secteur.secteur=boutiques.secteur
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where $in AND rechargecga.type=? and rechargecga.users LIKE CONCAT('%', ?, '%') $bSql $periodeString
                         order by rechargecga.id desc", array(PRE_FIN, $role))->result();

            var_dump($this->db->last_query());die;
        }

    }

    public function getRequestTvalidateByRole($role = false,$id=null)
    {

        $i="";
        if($id!=null){
            $i = " AND tickets.next_user=$id";
            if(isAssistantDfin($role) or isOperatriceTicketsFinanciers($role) or isOperatriceGestionCaisse())
                $i = " AND tickets.next_users LIKE CONCAT('%\"', $id, '\"%')";
        }
        setDB(DB_READ);
        if ($role) {
            if(isAssistantDfin($role) or isOperatriceTicketsFinanciers($role) or isOperatriceGestionCaisse()){
                return $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state, 
                        pr.status as prStatus,rechargecga.api_payement as api_payement,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         LEFT JOIN payment_references pr ON rechargecga.reference_id = pr.id
                         where rechargecga.valide=0 AND rechargecga.type=? AND tickets.next_roles LIKE CONCAT('%\"', ?, '\"%') $i
                         order by rechargecga.id desc", array(PRE_FIN, $role))->result();
            }
            else{
                return $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,
                        memos.state AS mState, memos.code AS mCode,pr.status as prStatus
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         LEFT JOIN payment_references pr ON rechargecga.reference_id = pr.id
                         where rechargecga.valide=1 AND rechargecga.type=? AND tickets.next_role = ? $i
                         order by rechargecga.id desc", array(PRE_FIN, $role))->result();
            }

        }
    }

    public function allRequest($state,$atime=null){

        if ($atime==null){
            $b="";
        }
        else{
            $b=" AND tickets.close_date like '%$atime%'";
        }
        setDB(DB_READ);
        return $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user, tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where rechargecga.type=? AND tickets.state = ? $b
                         order by rechargecga.id desc", array(PRE_FIN, $state))->result();
    }


    public function updateTable($data = array(), $table = false, $val = false, $field = false)
    {
        setDB(DB_WRITE);
        if (is_array($data) && $table && $val) {
            return $this->db->where('id', $val)->update($table, $data);
        } elseif (is_array($data) && $table && $val && $field) {
            return $this->db->where($field, $val)->update($table, $data);
        } else {
            return false;
        }
    }

    public function getSoldeCga($boutique = false,$date="")
    {
        setDB(DB_READ);
        $d="";
        if($date!="")
            $d = " AND jour = '$date'";
        return $this->db->query("
            SELECT *
            FROM soldecga
            WHERE boutique = ? $d
            ORDER BY jour desc
        ", array($boutique))->result();
    }

    public function getRequestsConsulting($valide, $dateS = null)
    {
        $d="";
        if($dateS!=null)
            $d = " AND tickets.close_date LIKE '%$dateS%'";
        setDB(DB_READ);

        $typeCga = array(STD,CDT,REX);

        if (empty($dateS))
            //Les demandes d'un DA précis
            $query = $this->db->query("
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                    moyen_payment.label, type_payment.id as type, tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     LEFT JOIN tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where tickets.state = ? and rechargecga.type in ? $d
                     order by rechargecga.id desc",
                array($valide, $typeCga))->result();
        else
            //Demandes d'une boutique traité à une date précise
            $query = $this->db->query("
                    select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                     moyen_payment.label, type_payment.id as type,
                     tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     left join tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where  tickets.state = ? and rechargecga.type in ? $d
                     order by rechargecga.id desc",
                array($valide, $typeCga))->result();


        return !empty($query) ? $query :array();
    }


    public function getRequestsConsulting1($valide,$debut,$fin)
    {
        $d="";
        //var_dump($debut);die;
        if($debut!=null && $fin!=null)
            $d = " AND (tickets.close_date >= '$debut') AND (tickets.close_date <='$fin') ";

        setDB(DB_READ);
        //Les demandes d'un DA précis
        {
            $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                        moyen_payment.label, type_payment.id as type, tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                        tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                        tickets.commentaire AS tkMotif
                         from rechargecga
                         LEFT JOIN tickets on rechargecga.ticket = tickets.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         where tickets.state = ? and rechargecga.type in(0,1) $d
                         order by rechargecga.id desc",
                array($valide))->result();

        }

        return !empty($query) ? $query :array();
    }




    public function getRequestsCompensationConsulting($valide, $dateS = null)
    {
        //$dateS=date("Y-m-d");
        setDB(DB_READ);
        if (empty($dateS))
            //Les demandes d'un DA précis
            $query = $this->db->query('
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                    moyen_payment.label, type_payment.id as type, tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     LEFT JOIN tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where tickets.state = ? and (rechargecga.moyen_paiement = ? or rechargecga.moyen_paiement = ?)
                     order by rechargecga.id desc',
                array($valide, 7, 8))->result();
        else
            //Demandes d'une boutique traité par un controleur précis
            $query = $this->db->query("
                    select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                     moyen_payment.label, type_payment.id as type,
                     tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     left join tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where  tickets.state = ? and (rechargecga.moyen_paiement = ? or rechargecga.moyen_paiement = ?)
                     order by rechargecga.id desc",
                array($valide, 7, 8))->result();


        return !empty($query) ? $query :array();
    }
    public  function getSumValidatedRefillCga($boutique, $date){
        setDB(DB_READ);
        $d = "  DATE(tickets.close_date)='$date'";
        $return = $this->db->query("
            SELECT SUM(rechargecga.montant) AS sumCga
            FROM rechargecga
            LEFT JOIN tickets on rechargecga.ticket = tickets.id
            WHERE tickets.state = ? and rechargecga.boutique = ? and $d
        ", array(FERME, $boutique))->result();

        return !empty($return) ? $return[0] :array();
    }

    public function getSumReabonnement_mtnAbo($boutique, $date){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT SUM(reabonnements.mtn_abo) AS sumMtnAbo
            FROM reabonnements
            WHERE reabonnements.boutique = ? and reabonnements.jour = ?
        ", array($boutique, $date))->result();

        return !empty($return) ? $return[0] :array();
    }

    public function  getSumReabonnement_mtnReabo($boutique, $date){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT SUM(reabonnements.mtn_reabo) AS sumMtnReabo
            FROM reabonnements
            WHERE reabonnements.boutique = ? and reabonnements.jour = ?
        ", array($boutique, $date))->result();



        return !empty($return) ? $return[0] :array();
    }

    public function  getSumReabonnement_mtnMatRecru($boutique, $date){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT SUM(recrutements.montant) AS sumRecru
            FROM recrutements
            WHERE recrutements.boutique = ? and recrutements.jour = ?
        ", array($boutique, $date))->result();

        return !empty($return) ? $return[0] :array();
    }
    public function  getSumReabonnement_mtnMatMigr($boutique, $date){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT SUM(migrations_dsi.mtn_abo) AS sumMigr
            FROM migrations_dsi
            WHERE migrations_dsi.boutique = ? and migrations_dsi.jour = ?
        ", array($boutique, $date))->result();

        return !empty($return) ? $return[0] :array();
    }

    public function getSumReabonnementSvod_mtnAbo($boutique, $date){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT SUM(reabo_svod.mtn_abo) AS sumMtnAbo
            FROM reabo_svod
            WHERE reabo_svod.boutique = ? and reabo_svod.jour = ?
        ", array($boutique, $date))->result();

        return !empty($return) ? $return[0] :array();
    }

    public function getSumReabonnementSvod_mtnReabo($boutique, $date){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT SUM(reabo_svod.mtn_reabo) AS sumMtnReabo
            FROM reabo_svod
            WHERE reabo_svod.boutique = ? and reabo_svod.jour = ?
        ", array($boutique, $date))->result();

        return !empty($return) ? $return[0] :array();
    }

    public function getSoldeCGAJourMoinsUn($boutique, $jour){
        setDB(DB_READ);
        $return = $this->db->query("
            SELECT (soldecga.montant) as montant,jour
            FROM soldecga
            WHERE soldecga.boutique = ? and jour<?
             ORDER BY jour desc LIMIT 1
        ", array($boutique, $jour))->result();

        return !empty($return) ? $return[0] :array();
    }

    public function getRequestsConsultingBySector($valide, $secteur, $dateS = null)
    {
        setDB(DB_READ);
        $d="";
        if($dateS!=null)
            $d = " AND DATE(tickets.close_date) = '$dateS'";
        if (empty($dateS))
            //Les demandes d'un DA précis
            $query = $this->db->query("
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                    moyen_payment.label, type_payment.id as type, tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     LEFT JOIN tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     LEFT JOIN secteur ON secteur.id = boutiques.secteur
                     where tickets.state = ? $d
                     and secteur.id = ?
                     order by rechargecga.id desc",
                array($valide, $secteur))->result();
        else
            //Demandes d'une boutique traité à une date précise
            $query = $this->db->query("
                    select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                     moyen_payment.label, type_payment.id as type,
                     tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     left join tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     LEFT JOIN secteur ON secteur.id = boutiques.secteur
                     where  tickets.state = ? $d
                     and secteur.id = ?
                     order by rechargecga.id desc",
                array($valide, $secteur))->result();


        return !empty($query) ? $query :array();
    }

    public function topFlopCga($cible=array(), $secteur=array()){
        setDB(DB_READ);
        $periodeString = generatePeriodeCriteria2(session_data('periode'),"tickets",false,false,false,'close_date');

        $this->db->select("boutiques.id, boutiques.nom, SUM(rechargecga.montant) montant, COUNT(tickets.id) nbrTicket")
            ->from("rechargecga")
            ->join("tickets", "rechargecga.ticket = tickets.id")
            ->join("boutiques", "rechargecga.boutique = boutiques.id")
            ->join("secteur", "secteur.id = boutiques.secteur")
            ->where("tickets.state = 1 $periodeString");

        if(is_array($cible) and !empty($cible))
            $this->db->where_in("boutiques.type", $cible);
        if(is_array($secteur) and !empty($secteur))
            $this->db->where_in("boutiques.secteur", $secteur);

        $this->db->group_by("boutiques.id");

        return $this->db->get()->result();
    }

    public function getMemos($id)
    {
        setDB(DB_READ);
        $this->db->select("*")
            ->from("memos")
            ->join("rechargecga", "rechargecga.memo = memos.id")
            ->where("memos.id=$id");
        return $this->db->get()->result();
    }


    public function listCollaborateurs($id_role=null){
        $result=array();
        if ($id_role!=null){
            $data = $this->db->query("select * from users where role in(select id from roles where departement = (select departement from roles where id = $id_role))")->result();
            foreach ($data as $items){
                $result[] = $items->id;
            }
        }
        return $result;
    }

    public function collabGetRequestsConsulting($valide, $dateS = null,$collaborators=null)
    {
        $d="";
        if($dateS!=null)
            $d = " AND tickets.close_date LIKE '%$dateS%'";
        if(is_array($collaborators) and !empty($collaborators)) {
            $list_collab = '(';
            for ($i = 0; $i < count($collaborators); $i++) {
                if ($i == count($collaborators) - 1)
                    $list_collab .= $collaborators[$i] . ')';
                else
                    $list_collab .= $collaborators[$i] . ',';
            }
            $periodeString = generatePeriodeCriteria2(session_data('periode'),"tickets",false,false,false,'open_date');
            if (empty($dateS))
                //Les demandes d'un DA précis
                $query = $this->db->query("
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                    moyen_payment.label, type_payment.id as type, tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     LEFT JOIN tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where tickets.init_user in $list_collab $periodeString
                     order by rechargecga.id desc" )->result();
            else
                //Demandes d'une boutique traité à une date précise
                $query = $this->db->query("
                    select rechargecga.*,rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.type AS btqType, boutiques.tel,
                     moyen_payment.label, type_payment.id as type,
                     tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,
                    tickets.next_role, tickets.commentaire, tickets.state,tickets.open_date,tickets.close_date,
                    memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.next_user AS mNext_user, memos.id AS mId, memos.date_paiement, memos.motif_invalidation,
                    tickets.commentaire AS tkMotif
                     from rechargecga
                     left join tickets on rechargecga.ticket = tickets.id
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN boutiques on rechargecga.boutique = boutiques.id
                     left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where tickets.init_user in $list_collab $periodeString
                     order by rechargecga.id desc" )->result();
            return $query;

        }else{
            return array();
        }

    }

    public function getAllRequestsCga($params = array()){
        $debut=$fin=$type="";
        if(!empty($params)){
            if(isset($params["debut"]))
                $debut = $params["debut"];
            if(isset($params["fin"]))
                $fin = $params["fin"];
            if(isset($params["type"]))
                $type = $params["type"];

            //$d=" AND  tk.open_date <= '$fin' AND tk.open_date >= '$debut' ";
            $d=" AND  tk.close_date >= '$debut' AND tk.close_date <= '$fin' ";
            return $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist, boutiques.tel as bTel, boutiques.type AS btqType,
                        moyen_payment.label, type_payment.id as type,
                        tk.state AS tState, tk.state, tk.num, tk.num AS tkNum, tk.id AS tkId, tk.next_user, tk.next_role,tk.open_date,tk.close_date,
                        memos.state AS mState, memos.code AS mCode, memos.users AS mUsers, memos.id AS mId,
                        u.nom AS cnom, u.prenom AS cpnom, u.tel, tk.commentaire AS tkMotif
                         FROM rechargecga
                         INNER JOIN tickets tk ON rechargecga.ticket = tk.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         WHERE tk.state = 1 AND rechargecga.type IN $type $d ORDER BY rechargecga.id ASC
                         ")->result();
        }

    }

    public function getAllRequestFinancial($params = array()){
        //$periodeString = generatePeriodeCriteria2(session_data('periode'),"tickets",false,false,false,'open_date');
        $debut=$fin=$type="";
        if(!empty($params)){
            if(isset($params["debut"]))
                $debut = $params["debut"];
            if(isset($params["fin"]))
                $fin = $params["fin"];
            if(isset($params["type"]))
                $type = $params["type"];

            $periodeString = "tickets.close_date >= '$debut' AND tickets.close_date <= '$fin' ";

            return $query = $this->db->query("
                    select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                  moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                  tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                    tickets.next_role, tickets.commentaire, tickets.state, tickets.commentaire AS tkMotif,
                    memos.state AS mState, memos.code AS mCode
                     from rechargecga
                     INNER JOIN tickets on rechargecga.ticket = tickets.id
                     INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                     LEFT JOIN aa_secteur ON aa_secteur.secteur=boutiques.secteur
                     LEFT JOIN memos ON memos.id = rechargecga.memo
                     LEFT JOIN users u ON u.id = rechargecga.cfinancier
                     LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                     LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                     where tickets.state = 1 AND rechargecga.type IN $type and $periodeString
                     order by rechargecga.id ASC")->result();
        }

    }

    public function getValidatedPaybacks($params = array())
    {
        $debut=$fin=$type="";
        if(!empty($params)){
            if(isset($params["debut"]))
                $debut = $params["debut"];
            if(isset($params["fin"]))
                $fin = $params["fin"];
            if(isset($params["type"]))
                $type = $params["type"];

            $periodeString = "rd.date <= '$fin' AND rd.date >= '$debut' ";

            $query=$this->db->select('rd.*, rd.id rdId, m.id as mId, m.boutique, m.code,mp.label as moyPay, tp.nom as typePay,  tm.nom as memoType, o.label, u.id as uId, u.ccivil, u.nom, u.prenom, r.role, m.date as mDate, b.nom as bNom')
                ->from('remboursement_dette rd')
                ->join('memos m', 'm.id=rd.memo', 'left')
                ->join('type_memo tm', 'tm.id=m.type', 'left')
                ->join('moyen_payment mp', 'mp.id=rd.moyen_paiement', 'left')
                ->join('type_payment tp', 'tp.id=mp.type', 'left')
                ->join('objets_memo o', 'o.id=m.objet', 'left')
                ->join('boutiques b', 'b.id=m.boutique')
                ->join('users u', 'u.id=m.user', 'left')
                ->join('roles r', 'u.role=r.id', 'left')
                ->where("$periodeString")
                ->where('rd.state', REMBOURSEMENT_VALIDE)
                ->order_by('rd.date', 'ASC')
                ->get()
                ->result();
            return !empty($query)?$query:array();
        }


    }
    /**
     * @return array|array[]|object|object[]
     * module de recharge cga et payement par api MOMO & OM
     */
    public function getPendingCgaPayments(){
        $this->db->select('pr.*, tk.id as tkId, tk.state as tkState, tk.open_date,cga.id as cgaId')
            ->from('rechargecga cga')
            ->join('tickets tk', 'tk.id=cga.ticket')
            ->join('payment_references pr','cga.reference_id=pr.id','left')
            ->where('cga.api_payement',VALIDE)
            ->where('tk.reject_by_cron',ENCOURS)
            ->where_in('tk.state', [ENCOURS])
            ->group_start()
            ->where_in('pr.status', [ENCOURS])
            ->or_where('pr.status IS NULL')
            ->group_end();
        return $this->db->get()->result();
    }
    public function getTicketPendingCgaPayments(){
        $this->db->select('pr.*, tk.*, tk.state as tkState, tk.open_date,cga.id as cgaId')
            ->from('rechargecga cga')
            ->join('tickets tk', 'tk.id=cga.ticket')
            ->join('payment_references pr','cga.reference_id=pr.id','left')
            ->where('cga.api_payement',VALIDE)
            ->where_in('tk.state', [ENCOURS])
            ->group_start()
            ->where_in('pr.status', [ENCOURS])
            ->or_where('pr.status IS NULL')
            ->group_end();
        return $this->db->get()->result();
    }


    public function getPendingFinancialPayments()
    {
        $this->db->select('pr.*, tk.id as tkId, tk.state as tkState, tk.open_date,cga.id as cgaId')
            ->from('rechargecga cga')
            ->join('tickets tk', 'tk.id=cga.ticket')
            ->join('payment_references pr','cga.reference_id=pr.id','left')
            ->where('cga.api_payement',VALIDE)
            ->where_in('tk.state', [ENCOURS])
            ->group_start()
            ->where_in('pr.status', [ENCOURS])
            ->or_where('pr.status IS NULL')
            ->group_end();
        return $this->db->get()->result();
    }

    public function getRequestToValidateCga($type, $params=array("typeRequest"=>'('.STD.')'))
    {
        setDB(DB_READ);

        $typeRequest = "";
        if(isset($params["typeRequest"]))
            $typeRequest = "AND rechargecga.type IN (".$params["typeRequest"].")";
        else
            $typeRequest = "AND rechargecga.type IN (".STD.")";

    $query = $this->db->query("
            select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
            moyen_payment.label, type_payment.id as type, u.nom as cnom, u.prenom as cpnom,u.tel,
            tickets.id as tkId, tickets.num as tkNum, tickets.num , tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
            tickets.next_role, tickets.commentaire, tickets.state,type_payment.nom as tNom,
            memos.state AS mState, memos.code AS mCode
             from rechargecga
             left join tickets on rechargecga.ticket = tickets.id
             LEFT JOIN memos ON memos.id = rechargecga.memo
             left join boutiques on rechargecga.boutique = boutiques.id
             LEFT JOIN users u ON u.id = rechargecga.cfinancier
             left join moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
             LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
             JOIN payment_references pr ON pr.id=rechargecga.reference_id
             where (rechargecga.valide=0 or pr.status=0) and tickets.state=0 and  rechargecga.api_payement=1 and tickets.type=? $typeRequest
             order by rechargecga.id desc",
        array($type))->result();
        return !empty($query) ? $query : array();
    }

    public function pendingFinacialrequest()
    {
        return $query = $this->db->query("
                        select rechargecga.*, rechargecga.type as rType, boutiques.nom, boutiques.numdist,boutiques.tel as bTel, boutiques.type AS btqType,
                      moyen_payment.label, type_payment.id as type,u.nom as cnom, u.prenom as cpnom,u.tel,
                      tickets.id as tkId, tickets.num, tickets.init_user, tickets.next_user,tickets.open_date,tickets.close_date,
                        tickets.next_role, tickets.commentaire, tickets.state, 
                        pr.status as prStatus,rechargecga.api_payement as api_payement,
                        memos.state AS mState, memos.code AS mCode
                         from rechargecga
                         INNER JOIN tickets on rechargecga.ticket = tickets.id
                         INNER JOIN boutiques on rechargecga.boutique = boutiques.id
                         LEFT JOIN memos ON memos.id = rechargecga.memo
                         LEFT JOIN users u ON u.id = rechargecga.cfinancier
                         LEFT JOIN moyen_payment on rechargecga.moyen_paiement = moyen_payment.id
                         LEFT JOIN type_payment ON type_payment.id = moyen_payment.type
                         LEFT JOIN payment_references pr ON rechargecga.reference_id = pr.id
                         where (rechargecga.valide=0 or pr.status=0) and tickets.state=0 AND rechargecga.type=? AND rechargecga.api_payement=1
                         order by rechargecga.id desc", array(PRE_FIN))->result();
    }

    public function getlisteCompteCga($user=NULL){
        if ($user!=null){
            $this->db->select("visu.*, CPT.login, CPT.mdp mdp, u1.nom user , r2.role role2, r1.role role1 ")
                ->from('compte_visus_cga CPT' )
                ->join('compte_visus_cga_users visu','visu.compte_visus_cga = CPT.id','left')
                ->join('users u1','u1.id = visu.users', 'left')
                ->join('users u2','u2.id = CPT.user','left')
                ->join('roles r1','r1.id = u1.role','left')
                ->join('roles r2','r2.id = u2.role', 'left')
                ->where('visu.compte_visus_cga = '.$user)
                ->where('visu.actif=1 and visu.responsable = 0')
                ->order_by("CPT.id", "DESC");
            return $this->db->get()->result();
        }else
            return false;
    }

    public function getResponCompteCga(){
        $this->db->select("visu.*, u0.nom respo,r1.role role1,CPT.login login,CPT.mdp mdp, u2.nom con, r2.role role2, 
         visu.actif actif, CPT.type_compte type, CPT.id icompte, CPT.state state")
            ->from('compte_visus_cga_users visu' )
            ->join('compte_visus_cga CPT','visu.compte_visus_cga = CPT.id','left')
            ->join('users u0','u0.id = visu.users','left')
            ->join('roles r1','r1.id = u0.role','left')
            ->join('users u2','u2.id = CPT.user','left')
            ->join('roles r2','r2.id = u2.role','left')
            ->where('visu.responsable =1')
            ->order_by("CPT.id", "DESC");
        return $this->db->get()->result();
    }

    public function getCompteByResponsable($responsable=null){

        $this->db->select("visu.*, u0.nom respo,r1.role role1,CPT.login login,CPT.mdp mdp, u2.nom con, r2.role role2, 
         visu.actif actif, CPT.id idcompte, CPT.type_compte type,CPT.state state ")
            ->from('compte_visus_cga_users visu' )
            ->join('compte_visus_cga CPT','visu.compte_visus_cga = CPT.id','left')
            ->join('users u0','u0.id = visu.users','left')
            ->join('roles r1','r1.id = u0.role','left')
            ->join('users u2','u2.id = CPT.user','left')
            ->join('roles r2','r2.id = u2.role','left')
            ->where('visu.users ='.$responsable);
        return $this->db->get()->result();
    }
    public function voirUserResponsable($responsable=null){

        $this->db->select(" u0.nom respo,r1.role role1,CPT.login login, visu.actif actif")
            ->from('compte_visus_cga_users visu' )
            ->join('compte_visus_cga CPT','visu.compte_visus_cga = CPT.id','left')
            ->join('users u0','u0.id = visu.users','left')
            ->join('roles r1','r1.id = u0.role','left')
            ->where('visu.responsable=1 and visu.compte_visus_cga ='.$responsable);
        return $this->db->get()->result();
    }

    public function getFilterCompteCga($idfiltre=null,$compte=null){

        $this->db->select("visu.*, u0.nom respo,r1.role role1,CPT.login login,CPT.mdp mdp, u2.nom con, r2.role role2, 
         visu.actif actif, CPT.type_compte type , CPT.id icompte, CPT.state state")
            ->distinct('visu')
            ->from('compte_visus_cga_users visu' )
            ->join('compte_visus_cga CPT','visu.compte_visus_cga = CPT.id','left')
            ->join('users u0','u0.id = visu.users','left')
            ->join('roles r1','r1.id = u0.role','left')
            ->join('users u2','u2.id = CPT.user','left')
            ->join('roles r2','r2.id = u2.role','left')
            ->order_by("CPT.id", "desc");
            
            if ($idfiltre==FILTRE_COMPTE_ACTIF ){
                    $this->db->where("CPT.state=1 and visu.responsable=1");
                }
            elseif ($idfiltre==FILTRE_COMPTE_BLOCQUE ){
                    $this->db->where("CPT.state=-1 and visu.responsable=1");
                }
            elseif ($idfiltre==FILTRE_COMPTE_VACANT ){
                    $this->db->where("CPT.state in (0,1) and visu.responsable !=1 and visu.actif !=1");
            }

            //var_dump($this->db->get_compiled_select()); die();
        return $this->db->get()->result();
    }
    public function getRechargecga($id=null,$state=null){
        $s = $state!==null?" AND t.state = $state":"";
        if(!$id)
            return $this->db->query("
            SELECT r.*,t.state,t.init_user, t.next_user,t.next_role,t.num,t.commentaire,t.close_date,t.open_date,t.id tkId
            FROM recharge_cga_canal r
            INNER JOIN tickets t ON r.ticket = t.id
            WHERE t.id !=0 $s
            ORDER BY r.date_creation DESC
          ")->result();
        return $this->db->query("
            SELECT r.*,t.state,t.init_user, t.next_user,t.next_role,t.num,t.commentaire,t.close_date,t.open_date,t.id tkId
            FROM recharge_cga_canal r
            INNER JOIN tickets t ON r.ticket = t.id
            WHERE r.id=? $s
            ORDER BY r.date_creation DESC
          ",array($id))->result();
    }

    public function getRechargecgaToTreat(){
            $id = session_data('id');
            $role = session_data('roleId');
            setDB(DB_READ);

            if(isAssistantCreditManager() || isAssistantDfin()){
                return $this->db->query("
            SELECT o.*,
            t.state,t.init_user, t.next_user,t.next_role,t.num,t.commentaire,t.close_date,t.open_date,t.id tkId,p.label
            FROM recharge_cga_canal o
            INNER JOIN tickets t ON o.ticket = t.id
            LEFT JOIN moyen_payment p ON p.id = o.moyen_paiement
            WHERE t.next_users like '%$id%' AND t.next_roles like '%$role%' AND t.state = ?
            ORDER BY o.date_creation DESC
          ",array(ENCOURS))->result();
            }else{
                return $this->db->query("
            SELECT o.*,
            t.state,t.init_user, t.next_user,t.next_role,t.num,t.commentaire,t.close_date,t.open_date,t.id tkId,p.label
            FROM recharge_cga_canal o
            INNER JOIN tickets t ON o.ticket = t.id
            LEFT JOIN moyen_payment p ON p.id = o.moyen_paiement
            WHERE t.next_user = ? AND t.next_role = ? AND t.state = ?
            ORDER BY o.date_creation DESC
          ",array($id,$role,ENCOURS))->result();
            }

        }

}