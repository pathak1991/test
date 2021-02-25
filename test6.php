<?php

class StudentBasicInfoForm extends CFormModel{

    use errorTrait;
    
    public $admissionId;
    public $fullName;
    public $email;
    public $passoutYear;
    public $instId;
    public $boardId;
    
    private $userMaster;
    
    public function rules() {
        return [
            ['fullName, email, instId', 'required'],
            ['email', 'email'],
            ['email', 'checkEmailId'],
            ['passoutYear', 'in', 'range' => range(date('Y') - 100, date('Y') + 5)],
            ['fullName', 'length', 'min' => 4, 'max' => 250],
            ['admissionId', 'length', 'min' => 1, 'max' => 25],
            ['admissionId', 'checkAdmissionId'],
        ];
    }
    
    public function checkAdmissionId(){
        if($this->admissionId){
            $SiDi = TblSiDi::model()->findByPk($this->instId);
            if(!$SiDi) return;
            
            if(TblUserMaster::model()->exists('admission_number = :number AND school_ref_id = :schoolId AND user_id <> :userId', [':number' => $this->admissionId, ':schoolId' => $SiDi->school_ref_id, ':userId' => $this->userMaster->user_id])){
                $this->addError('admissionId', 'Same admission number exists for another student');
            }
        }
    }
    
    public function checkEmailId(){
        if (substr(strtolower(trim($this->email)),-8) == '_deleted') {
            $this->addError('email', 'email already exists');
        } elseif(trim($this->userMaster->loginRef->username) !== trim($this->email)){
            $emailExists = LoginMaster::model()->exists('username = :email', [':email' => trim($this->email)]);
            if($emailExists){
                $this->addError('email', 'email already exists');
            }
            
            $testTaken = PsychometricTest::model()->exists('user_ref_id = :userId', [':userId' => $this->userMaster->user_id]);
            if($testTaken){
                $this->addError('email', sprintf('%s has taken one or more career tests, can not change email now', $this->userMaster->loginRef->username));
            }
        }
    }
    
    public function loadStudentInformation(){
        $this->admissionId = $this->userMaster->admission_number;
        $this->fullName = $this->userMaster->fullName;
        $this->email = $this->userMaster->loginRef->username;
        $this->passoutYear = $this->userMaster->passout_year;
        $this->boardId = $this->userMaster->present_board_ref_id;
        
        $SiStudent = TblSiStudents::model()->find('user_ref_id = :userId', [':userId' => $this->userMaster->user_id]);
        if($SiStudent){
            $this->instId = $SiStudent->inst_ref_id;
        }
    }
    
    public function updateStudentInformation(){
        
        $fullName = FullName::get_name($this->fullName);
        
        $this->userMaster->admission_number = $this->admissionId ? $this->admissionId : null;
        $this->userMaster->first_name = $fullName['fname'];
        $this->userMaster->last_name = $fullName['lname'];
        //$this->userMaster->passout_year = $this->passoutYear;
        //$this->userMaster->present_board_ref_id = $this->boardId;
        
//        if ($this->userMaster->passout_year < date('Y')) {
//            $class = 13;
//        } else {
//             $class = $this->userMaster->getPresentClass($this->userMaster->passout_year, true);
//        }
        
        //$this->userMaster->present_class = $class;
        //$this->userMaster->class_status = 'Pursuing';
        
//        if ($class == 13) {
//            $this->userMaster->present_class = 12;
//            $this->userMaster->class_status = 'Completed';
//        }
        
        $this->updateSchoolInformation();
        $this->changeEmailAddress();
        
        $this->userMaster->save(false);
        
        return true;
    }
    
    private function changeEmailAddress(){
        if(trim($this->userMaster->loginRef->username) !== trim($this->email)){
            $emailExists = LoginMaster::model()->exists('username = :email', [':email' => trim($this->email)]);
            $testTaken = PsychometricTest::model()->exists('user_ref_id = :userId', [':userId' => $this->userMaster->user_id]);
            
            if(!$emailExists && !$testTaken){
                $this->userMaster->loginRef->username = trim($this->email);
                $this->userMaster->loginRef->save(false);
            }
        }
    }
    
    private function updateSchoolInformation(){
        
        $SiDi = TblSiDi::model()->findByPk($this->instId);
        if(!$SiDi) return;
        
        $this->userMaster->school_ref_id = $SiDi->school_ref_id;
        $SiStudent = TblSiStudents::model()->find('user_ref_id = :userId', [':userId' => $this->userMaster->user_id]);
        if(!$SiStudent) $SiStudent = new TblSiStudents;
        
        $SiStudent->setAttributes([
            'user_ref_id' => $this->userMaster->user_id,
            'inst_ref_id' => $SiDi->inst_id,
            'is_invited' => 'N',
            'is_invitation_accepted' => null,
            'added_on' => date('Y-m-d H:i:s')
        ], false);
        
        $SiStudent->save(false);
        $this->userMaster->save(false);
    }
    
    public function setUserMaster(TblUserMaster $UserMaster){
        $this->userMaster = $UserMaster;
    }
    
    public function getUserClass(){
        return (int)$this->userMaster->present_class;
    }
    
    public function getInstitutes(){
        $Institutes = Yii::app()->db->createCommand()
                ->select('inst_id, inst_name')
                ->from('tbl_si_di')
                ->where('school_ref_id IS NOT NULL AND is_active = "Y"')
                ->queryAll();
        
        return CHtml::listData($Institutes, 'inst_id', 'inst_name');
    }
    
}


