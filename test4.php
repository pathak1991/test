<?php

class DataEntryAlumniStudents extends CFormModel{
    
    public $school_id;
    public $first_name;
    public $last_name;
    public $username;
    private $present_class = '12';
    public $phone;
    public $gender;
    public $college = [];
    public $course = [];
    public $status = [];
    public $scholarship;
    public $currency;
    public $amount;
    public $passout_year;
    
    private $send_contact_confirmation_mail = false;
    
    public function rules() {
        return [
            ['first_name, last_name, username, gender, passout_year', 'required'],
            ['username', 'email'],
            ['username', 'checkSchool'],
            ['passout_year', 'in', 'range' => range(date('Y') - 100, date('Y') + 5)],
            ['phone', 'validatePhone'],
            ['scholarship', 'validateScholarship'],
            ['college', 'validateCollege'],
            ['course', 'validateCourse'],
            ['status', 'validateStatus'],
            ['school_id, college, course, status', 'safe', 'on' => 'search']
        ];
    }
    
    public function addError($attribute, $error){
        return parent::addError($attribute, '<div class="holder"><p>'.$error.'</p></div>');
    }

    public function beforeValidate() {
        $this->username = preg_replace('/\s+/i', '+', trim($this->username));
        
        return parent::beforeValidate();
    }
    
    public function checkSchool($attribute, $params){
        $LoginMaster = LoginMaster::model()->find('username = :username', [':username' => $this->username]);
        if($LoginMaster){
            $UserMaster = TblUserMaster::model()->find('login_ref_id = ' . $LoginMaster->login_id);
            if($UserMaster && (int)$UserMaster->school_ref_id !== (int)$this->school_id){
                $this->addError('username', 'This student belongs to another ');
            }
            
            if((int)$LoginMaster->user_type_ref_id !== 1){
                $this->addError('username', 'Email exists but not a student');
            }
        }
        
    }
    
    public function validateScholarship($attribute, $params){
        if($this->{$attribute}){
            foreach(['currency', 'amount'] as $i) if(!$this->{$i}) $this->addError($i, $i . ' is required');
        }
    }
    
    public function validateCollege($attribute, $params){
        $required = true;
        foreach($this->{$attribute} as $i => $v){
            if(strlen(trim($v))){
                $required = false;
                if(!$this->course[$i]) $this->addError('course' . '['. $i .']', 'course is required');
                if(!$this->status[$i]) $this->addError('status' . '['. $i .']', 'status is required');
            }
        }
        
        if($required){
            reset($this->college);
            $this->addError('college' . '['. key($this->college) .']', 'college is required');
        }
    }
    
    public function validateCourse($attribute, $params){
        foreach($this->{$attribute} as $i => $v){
            if(strlen(trim($v))){
                if(!LookupDepartment::model()->exists('dept_alias = :course', [':course' => trim($v)])){
                    $this->addError($attribute . '['. $i .']', $attribute . ' is not found');
                }
            }
        }
    }
    
    public function validateStatus($attribute, $params){
        $joining = 0;
        foreach($this->{$attribute} as $i => $v){
            if($v == 'Joining') $joining++;
            if(strlen(trim($v))){
                if(!array_key_exists($v, $this->getStatusList())){
                    $this->addError($attribute . '['. $i .']', $attribute . ' is invalid');
                }
            }
        }
        if($joining >= 2){
            foreach($this->{$attribute} as $i => $v){
                if($v !== 'Joining') continue;
                $this->addError($attribute . '['. $i .']', 'Student can join one college only.');
            }
        }
    }
    
    public function checkCollegeExist($attribute, $params){
        if(!TblUniversityMaster::model()->exists('uni_name = :uni_name', [':uni_name' => trim($this->$attribute)])){
            $this->addError($attribute, $this->$attribute . ' is not found');
        }
    }
    
    public function validatePhone($attribute, $params){
//        if($this->$attribute){
//            if(!preg_match('/^[7-9][0-9]{9}$/', $this->$attribute)) $this->addError($attribute, 'Enter a valid phone number');
//        }
    }
    
    public function getSchoolName(){
        return $this->school_id ? Yii::app()->db->createCommand()->select('school_name')->from('lookup_school')->where('school_id = :id', [':id' => $this->school_id])->queryScalar() : null;
    }
    
    
    public function saveInfo(){
        $LoginMaster = LoginMaster::model()->find('username = :username', [':username' => strtolower($this->username)]);
        if(!$LoginMaster){
            $UserMaster = $this->registerStudent();
        }else{
            $UserMaster = TblUserMaster::model()->find('login_ref_id = ' . $LoginMaster->login_id);
        }
        
        if((int)$UserMaster->passout_year !== (int)$this->passout_year){
            $UserMaster->passout_year = $this->passout_year;
            $UserMaster->save(false);	
        }

        StudentOutplacementColleges::model()->deleteAll('user_ref_id = :user_id', [':user_id' => $UserMaster->user_id]);
        
        foreach($this->college as $index => $college){
            if(!strlen(trim($college))) continue;
            
            $College = TblUniversityMaster::model()->find('uni_name = :uni_name AND is_user_entered = "0" AND is_active = "Y"', [':uni_name' => trim($college)]);
            $Course = LookupDepartment::model()->find('dept_alias = :course', [':course' => trim($this->course[$index])]);
            
            if($College && $Course){
                
                /*
                $OutPlacement = StudentOutplacementColleges::model()->find('uni_ref_id = :uni_id AND user_ref_id = :user_id', [':uni_id' => $College->uni_id, ':user_id' => $UserMaster->user_id]);
                if(!$OutPlacement){
                    $OutPlacement = new StudentOutplacementColleges;
                    $OutPlacement->added_on = date('Y-m-d H:i:s');
                }
                */
                
                $OutPlacement = new StudentOutplacementColleges;
                $OutPlacement->setAttributes([
                    'dept_ref_id' => $Course->department_id,
                    'uni_ref_id' => $College->uni_id,
                    'status' => $this->status[$index],
                    'user_ref_id' => $UserMaster->user_id,
                    'added_on' => date('Y-m-d H:i:s'),
                ], false);
                
                if($this->status[$index] == 'Joining' && $this->scholarship){
                    $OutPlacement->scholarship_received = 1;
                    $OutPlacement->amount = $this->amount;
                    $OutPlacement->currency_ref_id = $this->currency;
                }
                
                $OutPlacement->save(false);
            }
        }
        
        if($this->send_contact_confirmation_mail){
            OutplacementPreferences::getUserPreferences($UserMaster->user_id);
            
            $SiDi = Yii::app()->db->createCommand()
                    ->from('tbl_si_di')
                    ->where('school_ref_id = ' . $this->school_id)
                    ->queryRow();
            
            $emailType = Yii::app()->db->createCommand()
                    ->select('unique_key')
                    ->from('email_type')
                    ->where('name = :name', [':name' => $SiDi['subdomain_slug_name']])
                    ->queryScalar();
            
            if(!$emailType){
                $unique_key = Yii::app()->db->createCommand('select UUID()')->queryScalar();
                $emailType = EmailType::addEmailType($SiDi['subdomain_slug_name'], $unique_key, $SiDi['inst_name'], 'alumnihelpdesk@univariety.com');
            }
            
            $student['user_id'] = $UserMaster->user_id;
            $student['inst_logo'] = $SiDi['inst_logo'];
            $student['inst_name'] = $SiDi['inst_name'];
            $student['name'] = ucwords($UserMaster->first_name . ' ' . $UserMaster->last_name);
            $student['subdomain_slug_name'] = $SiDi['subdomain_slug_name'];
            
//            $message = [];
//            $message['subject'] = sprintf('%s invites you to mentor your Juniors', $student['inst_name']);
//            // don't use alumni_can_contact.php template
//            //$message['body']    = Yii::app()->controller->renderFile(Yii::app()->basePath. '/views/email/html/alumni_can_contact.php', compact('student'), true);
//            $message['type']    = $emailType;
//            $message['toName']  = ucwords($UserMaster->first_name . ' ' . $UserMaster->last_name);
//            $message['toEmail'] = $UserMaster->loginRef->username;
//            $message['apiKey']  = Yii::app()->params['emailCron']['curlKey'];
//            $emailq  = new EmailMessage;
//            $emailq->addEmailMessage($message);
        }
        
        return true;
    }
    
    public function registerStudent(){
        $transaction = Yii::app()->db->beginTransaction();
        try {
            
            $url_details = LookupUrl::getUrlByModel(['model_name' => 'corePHP', 'class_level' => 0, 'label' => 'dashboard'], 1);

            $StudentSignup = new StudentSignup('new_signup');
            
            $LoginMaster = new LoginMaster;
            $LoginMaster->setAttributes([
                'username' => strtolower($this->username), 
                'pass_word' => DiHelper::genPassword(), 
                'user_type_ref_id' => 1, 
                'is_active' => 1, 
                'is_email_confirmed' => 0, 
                'is_systempwd' => 1, 
                'added_on' => date('Y-m-d H:i:s')
            ], false);

            if(!$LoginMaster->save(false)) throw new CDbException('login master save failed');

            $user_uno = $StudentSignup->generateUserUNO('IN');
            $user_membership_ref_id = $StudentSignup->getMembershipId('SI Student');

            $TblUserMaster = new TblUserMaster;
            $TblUserMaster->setAttributes([
                'login_ref_id' => $LoginMaster->login_id,
				'membership_ref_id' => 2,
                'school_ref_id' => $this->school_id,
                'uno' => $user_uno,
                'first_name' => ucwords(strtolower($this->first_name)), 
                'last_name' => ucwords(strtolower($this->last_name)),
                'membership_ref_id' => $user_membership_ref_id['membership_id'],
                'redirect_url_ref_id' => $url_details[0]['url_id'],
                'profile_status_ref_id' => 3,
                'present_class' => $this->present_class,
                'class_status' => 'Completed',
                'passout_year' => $this->passout_year,
                'passout_month' => 3,
                'gender' => $this->gender,
                'added_on' => date('Y-m-d H:i:s'),
            ], false);
            if(!$TblUserMaster->save(false)) throw new CDbException('user master save failed');
            StudentPreferredStudyLevel::addStudentPreferences($TblUserMaster->user_id, [5]);

            if($this->phone){
                $StudentContactNumber = new StudentContactNumber;
                $StudentContactNumber->setAttributes([
                    'user_ref_id' => $TblUserMaster->user_id,
                    'phone_type' => 'Mobile',
                    'contact_type_ref_id' => 1,
                    'country_code' => '91',
                    'number' => $this->phone,
                    'is_primary' => 1,
                    'added_by' => $LoginMaster->login_id
                ], false);
                if(!$StudentContactNumber->save(FALSE)) throw new CDbException('student contact number save failed');
            }

            TblUserProfCriticalCompletion::updateCriticalFields($TblUserMaster->user_id, ['name' => 1, 'gender' => 1, 'study_level' => 1, 'edu_study_level' => 1,'completion_year' => 1]);
            $StudentSignup->setRbacAssignment('Student', $LoginMaster->login_id);

            $TblSiDi = TblSiDi::model()->find('school_ref_id = :id', [':id' => $TblUserMaster->school_ref_id]);
            if($TblSiDi){
                TblSiStudents::model()->deleteAll('user_ref_id = :user_id', [':user_id' => $TblUserMaster->user_id]);
                $TblSiStudents = new TblSiStudents();
                $TblSiStudents->setAttributes([
                    'user_ref_id' => $TblUserMaster->user_id,
                    'inst_ref_id' => $TblSiDi->inst_id,
                    'is_invited' => 'N',
                    'added_on' => date('Y-m-d H:i:s')
                ], false);
                
                $TblSiStudents->save(false);
            }
            
            
            
            $transaction->commit();
        } catch (Exception $exc) {
            $transaction->rollback();
            return $exc->getMessage();
        }
        return $TblUserMaster;
    }
    
    public function dataProvider(){
        $criteria = new CDbCriteria;
        $criteria->with = [
            'deptRef' => ['select' => 'deptRef.department_id, deptRef.dept_alias', 'condition' => 'deptRef.department_id IS NOT NULL'],
            'uniRef' => ['select' => 'uniRef.uni_id, uniRef.uni_name', 'condition' => 'uniRef.uni_id IS NOT NULL AND uniRef.is_user_entered = "0" AND uniRef.is_active = "Y"'],
            'userRef' => ['select' => 'userRef.first_name, userRef.last_name, userRef.passout_year, userRef.school_ref_id'],
            'userRef.schoolRef' => ['select' => 'schoolRef.school_id, schoolRef.school_name', 'condition' => 'schoolRef.school_id IS NOT NULL'],
            'userRef.loginRef' => ['select' => 'loginRef.username'],
        ];
        
//        $criteria->join = implode(' ', [
//            'JOIN tbl_university_master tum ON tum.uni_id = t.uni_ref_id',
//            'JOIN tbl_user_master um ON um.user_id = t.user_ref_id',
//            'JOIN lookup_school ls ON ls.school_id = um.school_ref_id',
//            'JOIN lookup_department ld ON ld.department_id = t.dept_ref_id',
//        ]);
        
        if($this->school_id){
            $criteria->addCondition('schoolRef.school_id = :school_id');
            $criteria->params[':school_id'] = $this->school_id;
        }
        
        $criteria->order = 'userRef.passout_year DESC, schoolRef.school_id, t.uni_ref_id, t.dept_ref_id, t.status';
        new StudentOutplacementColleges; 
        return $dataProvider = new CActiveDataProvider('StudentOutplacementColleges', [
            'criteria' => $criteria,
            'pagination' => ['pageSize' => 20]
        ]);
    }
    
    public function schoolList(){
        $schools = Yii::app()->db->createCommand()
                ->select('ls.school_id, ls.school_name', false)
                ->from('lookup_school ls')
                ->join('tbl_si_di sd', 'sd.school_ref_id = ls.school_id')
                ->where('ls.user_entered = 0 AND sd.is_active = "Y" AND sd.is_test = "N"')
                ->order('ls.school_name')
                ->queryAll();

        return CHtml::listData($schools, 'school_id', 'school_name');
    }
    
    public function getCountryMobileCodes(){
        return CHtml::listData(TblCountryMaster::getCountryMobileCodes(), 'phone_code', 'country_name');
    }
    
    public function getStatusList(){
        $l = ['PlanningToApply', 'AwaitingResults', 'AcceptedOffer', 'GotOfferUndecided', 'GotOfferNotJoining', 'DidntGetOffer', 'Joining'];
        return array_combine($l, array_map(function($i) { return Inflector::humanize(Inflector::underscore($i)); }, $l));
    }
    
    public function getCurrencyList(){
        return LookupCurrency::getList('currency_id', 'currency_code');
    }
    
    
}


