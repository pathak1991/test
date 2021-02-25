<?php

class DataEntrySchoolGroups extends CActiveRecord {

    public static function model($className = __CLASS__) {
        return parent::model($className);
    }

    public function tableName() {
        return 'school_groups';
    }

    public function rules() {
        return [
            ['group_name', 'required'],
            ['group_name', 'length', 'max' => 250],
            ['added_on, modified_on', 'safe'],
            ['school_group_id, group_name, added_on, modified_on', 'safe', 'on' => 'search'],
        ];
    }

    public function behaviors(){
	return [
            'CTimestampBehavior' => [
                'class' => 'zii.behaviors.CTimestampBehavior',
                'createAttribute' => 'added_on',
                'updateAttribute' => 'modified_on',
            ],
        ];
    }
    
    public function attributeLabels() {
        return [
            'school_group_id' => 'School Group',
            'group_name' => 'Group Name',
            'added_on' => 'Added On',
            'modified_on' => 'Modified On',
        ];
    }

    public function addError($attribute, $error){
        return parent::addError($attribute, sprintf('<div class="holder"><p>%s</p></div>', $error));
    }


    public function search() {
        $criteria = new CDbCriteria;

        $criteria->compare('school_group_id', $this->school_group_id, true);
        $criteria->compare('group_name', $this->group_name, true);
        $criteria->compare('added_on', $this->added_on, true);
        $criteria->compare('modified_on', $this->modified_on, true);

        return new CActiveDataProvider($this, ['criteria' => $criteria]);
    }

}
