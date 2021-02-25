<?php

class AlumniValidation extends AlumniValidationFlags {

    const ACTION_STOP_CAMPAIGN = 1;
    const ACTION_START_CAMPAIGN = 2;
    const ACTION_MARK_VALID = 3;
    const ACTION_MARK_WRONG = 4;
    
    public $user_id;
    public $full_name;
    public $school_name;
    public $passout_year;
    public $curriculam;
    public $call_to_action;
    
    public function rules(){
        
        return [
            ['full_name', 'length', 'max' => 200],
            ['full_name, rule_1, rule_2, rule_3, rule_4, school_name, passout_year, curriculam', 'safe', 'on' => 'search'],
        ];
    }
    
    public function getCallToActionEnum(){
        return [
            self::ACTION_STOP_CAMPAIGN => 'Stop Campaign',
            self::ACTION_START_CAMPAIGN => 'Start Campaign',
            self::ACTION_MARK_VALID => 'Mark As Valid',
            self::ACTION_MARK_WRONG => 'Mark AS Wrong',
        ];
    }
    
    public function callToAction(){
        
        $Sql = 'UPDATE alumni_validation_flags t JOIN tbl_user_master um ON um.user_id = t.user_ref_id SET';
        $Where = ' WHERE um.is_test = "N"';
        
        if((int)$this->call_to_action == self::ACTION_MARK_VALID){
            $Sql .= sprintf(' t.validation_status = "%s"', self::VALIDATION_STATUS_VALID);
        }
        
        if((int)$this->call_to_action == self::ACTION_MARK_WRONG){
            $Sql .= sprintf(' t.validation_status = "%s"', self::VALIDATION_STATUS_WRONG);
        }
        
        if((int)$this->call_to_action == self::ACTION_START_CAMPAIGN){
            $Sql .= sprintf(' t.campaign_status = "%s"', self::CAMPAIGN_STATUS_IN_QUEUE);
            $Where .= sprintf(' AND (t.campaign_status <> "%s" OR t.campaign_status IS NULL) AND (t.rule_1 = 1 OR t.rule_2 = 1) AND t.emails_sent < 10', self::CAMPAIGN_STATUS_IN_PROGRESS);
        }
        
        if((int)$this->call_to_action == self::ACTION_STOP_CAMPAIGN){
            $Sql .= sprintf(' t.campaign_status = "%s"', self::CAMPAIGN_STATUS_STOPPED);
        }
        
        $Sql .= $Where;
        
        if($this->school_name) $Sql .= sprintf(' AND um.school_ref_id = %d', $this->school_name);
        if(strlen($this->passout_year)) $Sql .= sprintf(' AND um.passout_year = %d', $this->passout_year);
        if($this->curriculam) $Sql .= sprintf(' AND um.present_board_ref_id = %d', $this->curriculam);
        
        if($this->rule_1 && $this->rule_2){
            $Sql .= ' AND (t.rule_1 = 1 OR t.rule_2 = 1)';
        }else{
            if($this->rule_1) $Sql .= ' AND t.rule_1 = 1';
            if($this->rule_2) $Sql .= ' AND t.rule_2 = 1';
        }
        
        if($this->rule_3) $Sql .= ' AND t.rule_3 = 1';
        if($this->rule_4) $Sql .= ' AND t.rule_4 = 1';
        if($this->validation_status) $Sql .= sprintf(' AND t.validation_status = "%s"', $this->validation_status);
        if($this->campaign_status) $Sql .= sprintf(' AND t.campaign_status = "%s"', $this->campaign_status);
        
        Yii::app()->db->createCommand($Sql)->execute();
    }
    
    public function search() {
        
        $Criteria = new CDbCriteria;
        $Params = [];
        
        $Criteria->select = [
            't.id, um.user_id, CONCAT_WS(" ", um.first_name, um.last_name) AS full_name, t.campaign_status, t.validation_status',
            't.rule_1, t.rule_1_notes, t.rule_2, t.rule_2_notes, t.rule_3, t.rule_3_notes, t.rule_4, t.rule_4_notes, t.emails_sent'
        ];
        
        if($this->full_name){
            $Criteria->addSearchCondition('CONCAT_WS(" ", um.first_name, um.last_name)', $this->full_name);
        }
        $Criteria->join = 'JOIN tbl_user_master um ON um.user_id = t.user_ref_id AND um.is_test = "N"';
        
        $Criteria->compare('um.present_board_ref_id', $this->curriculam);
        $Criteria->compare('um.passout_year', $this->passout_year);
        $Criteria->compare('um.school_ref_id', $this->school_name);
        
        if($this->rule_1 && $this->rule_2){
            $Criteria->addCondition('t.rule_1 = 1 OR t.rule_2 = 1');
        }else{
            $Criteria->compare('t.rule_1', $this->rule_1);
            $Criteria->compare('t.rule_2', $this->rule_2);
        }
        
        $Criteria->compare('t.rule_3', $this->rule_3);
        $Criteria->compare('t.rule_4', $this->rule_4);
        $Criteria->compare('validation_status', $this->validation_status);
        $Criteria->compare('campaign_status', $this->campaign_status);
        
        if(!empty($Params)){
            $Criteria->params = $Params;
        }
        
        $Criteria->group = 't.user_ref_id';

        return new CActiveDataProvider($this, [
            'criteria' => $Criteria,
            'pagination' => ['pageSize' => 20]
        ]);
    }

}
