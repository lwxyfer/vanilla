<?php if (!defined('APPLICATION')) exit();

class CommentModel extends VanillaModel {
   /// <summary>
   /// Class constructor.
   /// </summary>
   public function __construct() {
      parent::__construct('Comment');
   }
   
   public function CommentQuery() {
      $this->SQL->Select('c.*')
         ->Select('iu.Name', '', 'InsertName')
         ->Select('iup.Name', '', 'InsertPhoto')
         ->Select('uu.Name', '', 'UpdateName')
         ->Select('du.Name', '', 'DeleteName')
         ->SelectCase('c.DeleteUserID', array('null' => '0', '' => '1'), 'Deleted')
         ->From('Comment c')
         ->Join('User iu', 'c.InsertUserID = iu.UserID', 'left')
         ->Join('Photo iup', 'iu.PhotoID = iup.PhotoID', 'left')
         ->Join('User uu', 'c.UpdateUserID = uu.UserID', 'left')
         ->Join('User du', 'c.DeleteUserID = du.UserID', 'left');
      $this->FireEvent('CommentQueryAfter');
   }
   
   public function Get($DiscussionID, $Limit, $Offset = 0) {
      $this->CommentQuery();
      return $this->SQL
         ->Where('c.DiscussionID', $DiscussionID)
         ->Where('c.Draft', '0')
         ->OrderBy('c.DateInserted', 'asc')
         ->Limit($Limit, $Offset)
         ->Get();
   }

   public function SetWatch($Discussion, $Limit, $Offset, $TotalComments) {
      // Record the user's watch data
      $Session = Gdn::Session();
      if ($Session->UserID > 0) {
         $CountWatch = $Limit + $Offset;
         if ($CountWatch > $TotalComments)
            $CountWatch = $TotalComments;
            
         if (is_numeric($Discussion->CountCommentWatch)) {
            // Update the watch data
            $this->SQL->Put(
               'UserDiscussion',
               array(
                  'CountComments' => $CountWatch,
                  'DateLastViewed' => Format::ToDateTime()
               ),
               array(
                  'UserID' => $Session->UserID,
                  'DiscussionID' => $Discussion->DiscussionID,
                  'CountComments <' => $CountWatch
               )
            );
         } else {
            // Insert watch data
            $this->SQL->Insert(
               'UserDiscussion',
               array(
                  'UserID' => $Session->UserID,
                  'DiscussionID' => $Discussion->DiscussionID,
                  'CountComments' => $CountWatch,
                  'DateLastViewed' => Format::ToDateTime()
               )
            );
         }
      }
   }

   public function GetCount($DiscussionID) {
      $this->FireEvent('GetCountBefore');
      return $this->SQL->Select('CommentID', 'count', 'CountComments')
         ->From('Comment')
         ->Where('DiscussionID', $DiscussionID)
         ->Where('Draft', '0')
         ->Get()
         ->FirstRow()
         ->CountComments;
   }
   
   public function GetID($CommentID) {
      $this->CommentQuery();
      return $this->SQL
         ->Where('c.CommentID', $CommentID)
         ->Get()
         ->FirstRow();
   }
   
   public function GetNew($DiscussionID, $LastCommentID) {
      $this->CommentQuery();      
      return $this->SQL
         ->Where('c.DiscussionID', $DiscussionID)
         ->Where('c.CommentID >', $LastCommentID)
         ->Where('c.Draft', '0')
         ->OrderBy('c.DateInserted', 'asc')
         ->Get();
   }
   
   /// <summary>
   /// Returns the offset of the specified comment in it's related discussion.
   /// </summary>
   /// <param name="CommentID" type="int">
   /// The comment id for which the offset is being defined.
   /// </param>
   public function GetOffset($CommentID) {
      $this->FireEvent('GetOffsetBefore');
      return $this->SQL
         ->Select('c2.CommentID', 'count', 'CountComments')
         ->From('Comment c')
         ->Join('Discussion d', 'c.DiscussionID = d.DiscussionID')
         ->Join('Comment c2', 'd.DiscussionID = c2.DiscussionID')
         ->Where('c2.CommentID <=', $CommentID)
         ->Where('c2.Draft', '0')
         ->Where('c.CommentID', $CommentID)
         ->Get()
         ->FirstRow()
         ->CountComments;
   }
   
   /**
    * Reindex comments for the search.
    *
    *  @param int $DiscussionID Optional. A discussion ID to index the comments for.
    */
   public function Reindex($DiscussionID = NULL) {
      $Search = Gdn::Factory('SearchModel');
      if($Search == NULL) {
         return;
      }
      
      // Get all of the comments to reindex.
      $this->SQL
         ->Select('d.DiscussionID, d.Name, d.FirstCommentID, d.CategoryID')
         ->Select('c.CommentID, c.Body, c.InsertUserID, c.DateInserted')
         ->Select('sd.DocumentID')
         ->From('Discussion d')
         ->Join('Comment c', 'c.DiscussionID = d.DiscussionID')
         ->Join('SearchDocument sd', 'sd.PrimaryID = c.CommentID and sd.TableName = \'Comment\'', 'left');
         
      if(!is_null($DiscussionID)) {
         $this->SQL->Where('d.DiscussionID', $DiscussionID);
      }
      
      $Data = $this->SQL->Get();
      
      while($Row = $Data->NextRow()) {
         // Only index the title with the first comment.
         if($Row->FirstCommentID == $Row->CommentID)
            $Keywords = $Row->Name . ' ' . $Row->Body;
         else
            $Keywords = $Row->Body;
         
         $Document = array(
            'Title' => $Row->Name,
            'Summary' => $Row->Body,
            'TableName' => 'Comment',
            'PrimaryID' => $Row->CommentID,
            'PermissionJunctionID' => $Row->CategoryID,
            'InsertUserID' => $Row->InsertUserID,
            'DateInserted' => $Row->DateInserted,
            'Url' => '/discussion/comment/'.$Row->CommentID.'/#Comment_'.$Row->CommentID,
         );
         
         if(!is_null($Row->DocumentID)) {
            $Document['DocumentID'] = $Row->DocumentID;
         }
         
         $Search->Index($Document, $Keywords);
      }
   }
   
   public function Save($FormPostValues) {
      $Session = Gdn::Session();
      
      // Define the primary key in this model's table.
      $this->DefineSchema();
      
      // Add & apply any extra validation rules:      
      $this->Validation->ApplyRule('Body', 'Required');
      $MaxCommentLength = Gdn::Config('Vanilla.Comment.MaxLength');
      if (is_numeric($MaxCommentLength) && $MaxCommentLength > 0) {
         $this->Validation->SetSchemaProperty('Body', 'Length', $MaxCommentLength);
         $this->Validation->ApplyRule('Body', 'Length');
      }
      
      $CommentID = ArrayValue('CommentID', $FormPostValues);
      $CommentID = is_numeric($CommentID) && $CommentID > 0 ? $CommentID : FALSE;
      $Insert = $CommentID === FALSE;
      if ($Insert)
         $this->AddInsertFields($FormPostValues);
      else
         $this->AddUpdateFields($FormPostValues);
      
      // Validate the form posted values
      if ($this->Validate($FormPostValues, $Insert)) {
         // If the post is new and it validates, check for spam
         if (!$Insert || !$this->CheckForSpam('Comment')) {
            $Fields = $this->Validation->SchemaValidationFields();
            $Fields = RemoveKeyFromArray($Fields, $this->PrimaryKey);
            if ($Insert === FALSE) {
               // If switching from draft to post, update the dateinserted
               
               if ($Fields['Draft'] == '0') {
                  $OldComment = $this->GetID($CommentID);
                  if ($OldComment->Draft == '1')
                     $Fields['DateInserted'] = Format::ToDateTime();

               }
               $this->SQL->Put($this->Name, $Fields, array('CommentID' => $CommentID));
            } else {
               // Make sure that the comments get formatted in the method defined by Garden
               $Fields['Format'] = Gdn::Config('Garden.InputFormatter', '');
               $CommentID = $this->SQL->Insert($this->Name, $Fields);
            }
            // Record user-comment activity if this comment is not a draft
            $Draft = ArrayValue('Draft', $Fields);
            $Draft = $Draft == '1' ? TRUE : FALSE;
            if (!$Draft) {
               $DiscussionID = ArrayValue('DiscussionID', $Fields);
               if ($DiscussionID !== FALSE)
                  $this->RecordActivity($DiscussionID, $Session->UserID, $CommentID);

               $this->UpdateCommentCount($CommentID);
               
               // Update the discussion author's CountUnreadDiscussions (ie.
               // the number of discussions created by the user that s/he has
               // unread messages in) if this comment was not added by the
               // discussion author.
               $Data = $this->SQL
                  ->Select('d.InsertUserID')
                  ->Select('d.DiscussionID', 'count', 'CountDiscussions')
                  ->From('Discussion d')
                  ->Join('Comment c', 'd.DiscussionID = c.DiscussionID')
                  ->Join('UserDiscussion w', 'd.DiscussionID = w.DiscussionID and w.UserID = d.InsertUserID')
                  ->Where('w.CountComments >', 0)
                  ->Where('c.InsertUserID', $Session->UserID)
                  ->Where('c.InsertUserID <>', 'd.InsertUserID', TRUE, FALSE)
                  ->Where('d.Draft', '0')
                  ->GroupBy('d.InsertUserID')
                  ->Get();
               
               if ($Data->NumRows() > 0) {
                  $UserData = $Data->FirstRow();
                  $this->SQL
                     ->Update('User')
                     ->Set('CountUnreadDiscussions', $UserData->CountDiscussions)
                     ->Where('UserID', $UserData->InsertUserID)
                     ->Put();
               }
               
               // Index the post.
               $Search = Gdn::Factory('SearchModel');
               if(!$Draft && !is_null($Search)) {
                  if(array_key_exists('Name', $FormPostValues) && array_key_exists('CategoryID', $FormPostValues)) {
                     $Title = $FormPostValues['Name'];
                     $CategoryID = $FormPostValues['CategoryID'];
                  } else {
                     // Get the name from the discussion.
                     $Row = $this->SQL
                        ->GetWhere('Discussion', array('DiscussionID' => $DiscussionID))
                        ->FirstRow();
                     if(is_object($Row)) {
                        $Title = $Row->Name;
                        $CategoryID = $Row->CategoryID;
                     }
                  }
                  
                  $Offset = $this->GetOffset($CommentID);
                  
                  // Index the discussion.
                  $Document = array(
                     'TableName' => 'Comment',
                     'PrimaryID' => $CommentID,
                     'PermissionJunctionID' => $CategoryID,
                     'Title' => $Title,
                     'Summary' => $FormPostValues['Body'],
                     'Url' => '/discussion/comment/'.$CommentID.'/#Comment_'.$CommentID,
                     'InsertUserID' => $Session->UserID);
                  $Search->Index($Document, $Offset == 1 ? $Document['Title'] . ' ' . $Document['Summary'] : NULL);
               }
            }
            $this->UpdateUser($Session->UserID);
         }
      }
      return $CommentID;
   }
      
   public function RecordActivity($DiscussionID, $ActivityUserID, $CommentID) {
      // Get the author of the discussion
      $DiscussionModel = new DiscussionModel();
      $Discussion = $DiscussionModel->GetID($DiscussionID);
      if ($Discussion->InsertUserID != $ActivityUserID) 
         AddActivity(
            $ActivityUserID,
            'DiscussionComment',
            '',
            $Discussion->InsertUserID,
            'discussion/comment/'.$CommentID.'/#Comment_'.$CommentID
         );
   }
   
   /// <summary>
   /// Updates the CountComments value on the discussion based on the CommentID
   /// being saved. 
   /// </summary>
   /// <param name="CommentID" type="int">
   /// The CommentID relating to the discussion we are updating.
   /// </param>
   public function UpdateCommentCount($CommentID) {
      $this->FireEvent('UpdateCommentCountBefore');
      $Data = $this->SQL
         ->Select('c2.DiscussionID')
         ->Select('c2.DiscussionID', 'count', 'CountComments')
         ->From('Comment c')
         ->Join('Comment c2', 'c.DiscussionID = c2.DiscussionID')
         ->Join('Discussion d', 'c2.DiscussionID = d.DiscussionID')
         ->Where('c.CommentID', $CommentID)
         ->Where('c2.Draft', '0')
         ->Where('c2.CommentID <>', 'd.FirstCommentID', TRUE, FALSE)
         ->GroupBy('c2.DiscussionID')
         ->Get()
         ->FirstRow();
      $Count = $Data ? $Data->CountComments : 0;
      
      if ($Count > 0) {
         $Data = $this->SQL
            ->Select('DateInserted, CommentID, DiscussionID')
            ->From('Comment')
            ->Where('DiscussionID', $Data->DiscussionID)
            ->OrderBy('DateInserted', 'desc')
            ->Limit(1, 0)
            ->Get()
            ->FirstRow();
      
         $this->SQL
            ->Update('Discussion')
            ->Set('DateLastComment', $Data->DateInserted)
            ->Set('LastCommentID', $Data->CommentID)
            ->Set('CountComments', $Count)
            ->Where('DiscussionID', $Data->DiscussionID)
            ->Put();
      }
   }
   
   public function UpdateUser($UserID) {
      // 1. Retrieve a draft count
      $CountDrafts = $this->SQL
         ->Select('CommentID', 'count', 'CountDrafts')
         ->From('Comment')
         ->Where('InsertUserID', $UserID)
         ->Where('Draft', '1')
         ->Get()
         ->FirstRow()
         ->CountDrafts;
         
      // 2. Retrieve a comment count (don't include FirstCommentIDs)
      $CountComments = $this->SQL
         ->Select('c.CommentID', 'count', 'CountComments')
         ->From('Comment c')
         ->Join('Discussion d', 'c.DiscussionID = d.DiscussionID and c.CommentID <> d.FirstCommentID')
         ->Where('c.InsertUserID', $UserID)
         ->Where('c.Draft', '0')
         ->Get()
         ->FirstRow()
         ->CountComments;
      
      // Save them to the attributes column of the user table for this user.
      $this->SQL
         ->Update('User')
         ->Set('CountDrafts', $CountDrafts)
         ->Set('CountComments', $CountComments)
         ->Where('UserID', $UserID)
         ->Put();
   }
   
   public function Delete($CommentID) {
      $this->EventArguments['CommentID'] = $CommentID;

      // Check to see if this is the first comment in the discussion
      $Data = $this->SQL
         ->Select('d.DiscussionID, d.FirstCommentID')
         ->From('Discussion d')
         ->Join('Comment c', 'd.DiscussionID = c.DiscussionID')
         ->Where('c.CommentID', $CommentID)
         ->Get()
         ->FirstRow();
         
      if ($Data) {
         if ($Data->FirstCommentID == $CommentID) {
            $DiscussionModel = new DiscussionModel();
            $DiscussionModel->Delete($Data->DiscussionID);
         } else {
            $this->FireEvent('DeleteComment');
            // Delete the comment
            $this->SQL->Delete('Comment', array('CommentID' => $CommentID));
         }
      }
      return TRUE;
   }   
}