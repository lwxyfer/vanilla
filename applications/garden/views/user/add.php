<?php if (!defined('APPLICATION')) exit();

echo $this->Form->Open(array('class' => 'User'));
echo $this->Form->Errors();
?>
<h1><?php
   if (is_object($this->User))
      echo Gdn::Translate('Edit User');
   else
      echo Gdn::Translate('Add User');
?></h1>
<ul>
   <li>
      <?php
         echo $this->Form->Label('Username', 'Name');
         echo $this->Form->TextBox('Name');
      ?>
   </li>
   <li>
      <?php
         echo $this->Form->Label('Password', 'Password');
         echo $this->Form->Input('Password', 'password');
      ?>
      <div>
         <?php
            echo $this->Form->Label('&nbsp;');
            echo Anchor('Generate Password', '#', 'GeneratePassword');
            echo Anchor('Reveal Password', '#', 'RevealPassword');
         ?>
      </div>
   </li>
   <li>
      <?php
         echo $this->Form->Label('Email', 'Email');
         echo $this->Form->TextBox('Email');
      ?>
   </li>
   <li>
      <?php
         echo $this->Form->Label('&nbsp;');
         echo $this->Form->CheckBox('ShowEmail', 'Email visible to other users');
      ?>
   </li>
   <li>
      <strong><?php echo Gdn::Translate('Check all roles that apply to this user:'); ?></strong>
      <?php echo $this->Form->CheckBoxList("RoleID", $this->RoleData, $this->UserRoleData, array('TextField' => 'Name', 'ValueField' => 'RoleID')); ?>
   </li>
</ul>
<?php echo $this->Form->Close('Save');