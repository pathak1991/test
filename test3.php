<?php

class DataEntryAlumniCaseStudyOffers extends CFormModel{
    
    public $college_name = [];
    public $course_name = [];
    public $is_joined = [];
    public $scholarship = [];
    public $currency = [];

    public function rules() {
        return [
            ['college_name', 'validateCollege'],
            ['course_name', 'validateCourse'],
            ['is_joined', 'validateStatus'],
            
            ['scholarship, currency', 'safe'],
        ];
    }
    
    public function validateCollege($attribute){
        $required = true;
        foreach($this->{$attribute} as $i => $v){
            if(strlen(trim($v))){
                $required = false;
                
                if(!TblUniversityMaster::model()->exists('uni_name = :name', [':name' => trim($v)])){
                    $this->addError($attribute . '['.$i.']', "college not found");
                }
                
                if(!$this->course_name[$i]) $this->addError('course_name' . '['. $i .']', 'course is required');
                //if(!$this->is_joined[$i]) $this->addError('is_joined' . '['. $i .']', 'status is required');
            }
        }
        
        if($required){
            reset($this->$attribute);
            $this->addError($attribute . '['. key($this->$attribute) .']', 'college is required');
        }
    }
    
    public function validateCourse($attribute){
        foreach($this->{$attribute} as $i => $v){
            if(strlen(trim($v))){
                if(!LookupDepartment::model()->exists('dept_alias = :course', [':course' => trim($v)])){
                    $this->addError($attribute . '['. $i .']', $attribute . ' is not found');
                }
            }
        }
    }
    
    public function validateStatus($attribute){
        $joining = 0;
        foreach($this->{$attribute} as $i => $v){
            if((int)$v == 1) $joining++;
        }
        if($joining >= 2){
            foreach($this->{$attribute} as $i => $v){
                if((int)$v !== 1) continue;
                $this->addError($attribute . '['. $i .']', 'Student can join one college only.');
            }
        }
    }
    
    
}


