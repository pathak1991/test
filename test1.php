<?php

class DataEntryAlumniBulkUpload extends CFormModel{
    
    public $classLevel;
    public $SiDi;
    public $uniqueId;
    public $passoutYear;
    public $email;
    public $firstName;
    public $lastName;
    public $gender;
    public $mobile;
    public $boardName;
    public $overAllMarks;
    public $consent;
    
    public $collegeId;
    public $courseName;
    public $collegeStatus;
    public $scholarship;
    public $amount;
    public $currencyId;
    public $isPercentage;
    public $isFinalized;
    
    public $entranceId;
    public $testYear;
    public $testStatus;
    public $testScore;
    
    public function rules() {
        return [
            ['uniqueId, passoutYear, email, firstName, lastName, gender', 'required', 'on' => 'Profile'],
            ['boardName', 'required', 'on' => 'Marks'],
            ['boardName', 'validateBoardName', 'on' => 'Marks'],
            ['collegeId, courseName, collegeStatus, scholarship, isFinalized', 'required', 'on' => 'CollegeData'],
            ['collegeId', 'validateCollegeName', 'on' => 'CollegeData'],
            ['entranceId, testYear, testStatus, testScore, isFinalized', 'required', 'on' => 'TestData'],
            
            ['overAllMarks, amount, currencyId, isPercentage, mobile, consent', 'safe'],
        ];
    }
    
    public function validateCollegeName(){
        if(is_numeric($this->collegeId) && !TblUniversityMaster::model()->findByPk($this->collegeId)){
            $this->addError('collegeId', 'Invalid college id ' . $this->collegeId);
        }
    }
    
    public function validateBoardName(){
        if(!LookupBoardEquivalent::model()->find('board_name = :boardName AND class_level = "12"', [':boardName' => $this->boardName])){
            $this->addError('boardName', 'Invalid board name ' . $this->boardName);
        }
    }
    
    public function registerNewAccount(){
        $transaction = Yii::app()->db->beginTransaction();
        
        try{
            $urlDetails = LookupUrl::getUrlByModel(['model_name' => 'corePHP', 'label' => 'dashboard'], 1);

            $LoginMaster = new LoginMaster;
            $LoginMaster->setAttributes([
                'username' => $this->email,
                'pass_word' => uniqid(),
                'user_type_ref_id' => LoginMaster::USER_TYPE_STUDENT,
                'is_active' => 1,
                'is_email_confirmed' => 0,
                'added_on' => date('Y-m-d H:i:s'),
            ], false);

            $LoginMaster->save(false);

            $StudentSignup = new StudentSignup();
            $userUno = $StudentSignup->generateUserUNO('IN');

            $User = new TblUserMaster;
            $User->setAttributes([
                'login_ref_id' => $LoginMaster->login_id,
                'uno' => $userUno,
                'first_name' => ucwords($this->firstName),
                'last_name' => ucwords($this->lastName),
                'gender' => $this->gender,
                'present_class' => '12',
                'class_status' => 'Completed',
                'passout_year' => $this->passoutYear,
                'passout_month' => 4,
                'school_ref_id' => $this->SiDi->school_ref_id,
                'membership_ref_id' => 2,
                'redirect_url_ref_id' => $urlDetails[0]['url_id'],
                'profile_status_ref_id' => 3,
                'added_on' => date('Y-m-d H:i:s')
            ], false);

            $User->save(false);

            StudentPreferredStudyLevel::addStudentPreferences($User->user_id, [5]);
            $studentSignupModel = new StudentSignup();
            $studentSignupModel->setRbacAssignment('Student', $LoginMaster->login_id);

            $SiStudent = new TblSiStudents;
            $SiStudent->setAttributes([
                'user_ref_id' => $User->user_id,
                'inst_ref_id' => $this->SiDi->inst_id,
                'is_invited' => 'N',
                'is_invitation_accepted' => 'N',
                'class_level' => $this->classLevel,
                'added_on' => date('Y-m-d H:i:s')
            ], false);
            $SiStudent->save(false);

            $studyDurationObj = new StudentPreferredDuration();
            $studyDurationObj->insertStudyDurationByUserId($User->user_id, 4);
            $studyDurationObj->insertStudyDurationByUserId($User->user_id, 5);
            
            $criticalFields = ['name' => 1, 'gender' => 1, 'study_level' => 1, 'preferred_course_duration' => 1, 'edu_study_level' => 1, 'completion_year' => 1];
            
            if($this->mobile){
                $StudentContactNumber = new StudentContactNumber();
                $StudentContactNumber->setAttributes([
                    'contact_type_ref_id' => 1,
                    'country_code' => '91',
                    'number' => $this->mobile,
                    'user_ref_id' => $User->user_id,
                    'is_primary' => 1,
                    'added_on' => date('Y-m-d H:i:s'),
                    'added_by' => $LoginMaster->login_id,
                    'phone_type' => 'Mobile',
                ], false);
                $StudentContactNumber->save(false);
                $criticalFields['phone'] = 1;
            }

            TblUserProfCriticalCompletion::updateCriticalFields($User->user_id, $criticalFields);
            CommonUtils::calculateCriticalPercentage($User->user_id);
            
            $transaction->commit();
        }catch(Exception $e){
            $transaction->rollback();
            echo $e->getMessage(); exit;
            return false;
        }
        
        return $LoginMaster;
    }
    
    public function updateOrAddMarks(TblUserMaster $UserMaster){
        $Board = LookupBoardEquivalent::model()->find('board_name = :boardName AND class_level = "12"', [':boardName' => trim($this->boardName)]);
        $UserMaster->present_board_ref_id = $Board->board_ref_id;
        $UserMaster->save(false);
        
        $Education = StudentEducation::model()->find('class_level = "12" AND user_ref_id = :userId', [':userId' => $UserMaster->user_id]);
        if(!$Education){
            $Education = new StudentEducation;
            $Education->setAttributes([
                'user_ref_id' => $UserMaster->user_id,
                'year' => $UserMaster->passout_year,
                'class_level' => '12',
                'status' => $UserMaster->class_status,
                'added_on' => date('Y-m-d H:i:s'),
            ], false);
        }
        
        $Education->board_ref_id = $Board->board_equivalent_id;
        $Education->overall_marks = $this->overAllMarks;
        $Education->school_name = $UserMaster->school_ref_id ? $UserMaster->schoolRef->school_name : null;
        if(!$Education->save(false)){
            throw new CDbException(json_encode($Education->getErrors()));
        }
        
        StudentGradeCount::model()->deleteAll('student_education_ref_id = ' . $Education->student_education_id);
    }
    
    public function updateCollegeData(TblUserMaster $User){
        
        $OutplacementPreferences = OutplacementPreferences::getUserPreferences($User->user_id);
        
        $collegeId = null; $collegeName = null;
        if(is_numeric($this->collegeId)){
            $OutplacementCollege = StudentOutplacementColleges::model()->find('user_ref_id = :userId AND uni_ref_id = :uniId', [
                ':userId' => $User->user_id,
                ':uniId' => $this->collegeId,
            ]);
            $collegeId = $this->collegeId;
        }else{
            $collegeName = $this->collegeId;
            $College = TblUniversityMaster::model()->find('uni_name = :uniName AND is_user_entered = "0"', [':uniName' => trim($this->collegeId)]);
            if($College){
                $OutplacementCollege = StudentOutplacementColleges::model()->find('user_ref_id = :userId AND uni_ref_id = :uniId', [
                    ':userId' => $User->user_id,
                    ':uniId' => $College->uni_id,
                ]);
                $collegeId = $College->uni_id;
            }else{
                $OutplacementCollege = StudentOutplacementColleges::model()->find('user_ref_id = :userId AND uni_name = :uniName', [
                    ':userId' => $User->user_id,
                    ':uniName' => trim($this->collegeId),
                ]);
            }
        }
        
        if(!$OutplacementCollege){
            $OutplacementCollege = new StudentOutplacementColleges;
            $OutplacementCollege->added_on = date('Y-m-d H:i:s');
        }
        
        $Course = LookupDepartment::model()->find('department_name = :name OR dept_alias = :name', [':name' => $this->courseName]);
        
        if(($this->scholarship == 'Y' && $this->currencyId) && !is_numeric($this->currencyId)){
            $Currency = LookupCurrency::model()->find('currency_code = :code', [':code' => $this->currencyId]);
            if(!$Currency){
                throw new CDbException('Invalid currency code ' . $this->currencyId);
            }
            $this->currencyId = $Currency->currency_id;
            $OutplacementPreferences->no_scholarship = 0;
        }
        
        if($this->scholarship == 'N'){
            $OutplacementPreferences->no_scholarship = 1;
        }
        
        $OutplacementCollege->setAttributes([
            'user_ref_id' => $User->user_id,
            'uni_ref_id' => $collegeId ? $collegeId : null,
            'status' => $this->collegeStatus,
            'dept_ref_id' => $Course ? $Course->department_id : null,
            'scholarship_received' => ($this->scholarship == 'Y') ? 1 : 2,
            'amount' => ($this->scholarship == 'Y') ? $this->amount : null,
            'amount_type' => ($this->isPercentage == 'Y') ? 'Percentage' : 'Amount',
            'currency_ref_id' => ($this->scholarship == 'Y' && $this->isPercentage !== 'Y') ? $this->currencyId : null,
            'uni_name' => $collegeId ? null : $collegeName,
            'others' => $Course ? null : $this->courseName,
        ], false);
        
        if(!$OutplacementCollege->save(false)) throw new CDbException(json_encode($OutplacementCollege->getErrors()));
        UserOutplacement::addOutplacement($User->user_id, UserOutplacement::OUTPLACEMENT_COLLEGES);
        UserOutplacement::addOutplacement($User->user_id, UserOutplacement::OUTPLACEMENT_COURSES);
        $OutplacementPreferences->save(false);
    }
    
    public function updateTestData(TblUserMaster $User){
        
        if(is_numeric($this->entranceId)){
            $Entrance = EntranceExam::model()->findByPk($this->entranceId);
            if(!$Entrance) throw new CDbException('Entrance not found ' . $this->entranceId);
        }else{
            $Entrance = EntranceExam::model()->find('entrance_short_name = :name', [':name' => $this->entranceId]);
            if(!$Entrance) throw new CDbException('Entrance not found ' . $this->entranceId);
            $this->entranceId = $Entrance->entrance_id;
        }
        
        $StudentOutplacementTest = StudentOutplacementTests::model()->find('user_ref_id = :userId AND entrance_ref_id = :entranceId', [':userId' => $User->user_id, ':entranceId' => $this->entranceId]);
        if(!$StudentOutplacementTest){
            $StudentOutplacementTest = new StudentOutplacementTests();
            $StudentOutplacementTest->added_on = date('Y-m-d H:i:s');
        }
        
        $StudentOutplacementTest->setAttributes([
            'user_ref_id' => $User->user_id,
            'entrance_ref_id' => $this->entranceId,
            'year' => $this->testYear,
            'score' => $this->testScore,
            'status' => $this->testStatus,
        ], false);
        
        if(!$StudentOutplacementTest->save(false)) throw new CDbException(json_encode($StudentOutplacementTest->getErrors()));
        
        UserOutplacement::addOutplacement($User->user_id, UserOutplacement::OUTPLACEMENT_SCHOLARSHIP);
    }
    
}


