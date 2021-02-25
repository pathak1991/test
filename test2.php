<?php

class DataEntryAlumniCaseStudyData extends CFormModel{

    public $section = [];
    public $title = [];
    public $text = [];
    public $video = [];

    public function rules() {
        return [
            ['title', 'validateTitle'],
            
            ['section, title, text, video', 'safe'],
        ];
    }
    
    public function validateTitle($attribute){
        $required = true;
        foreach($this->{$attribute} as $i => $v){
            if(strlen(trim($v))){
                $required = false;
                if(!$this->text[$i]) $this->addError('text' . '['. $i .']', 'Case study info is required');
            }
        }
        
        if($required){
            reset($this->$attribute);
            $this->addError($attribute . '['. key($this->$attribute) .']', 'Case study title required');
        }
    }
    
    public function getSections(){
        return [
            'Heighlites' => 'Heighlites',
            'Journey' => 'Journey'
        ];
    }
    
}


