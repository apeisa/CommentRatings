<?php


class CommentFormWithRatings extends CommentForm  {

	public function getOptionsArray() {
		$options = array();

		if ($this->commentsField->ratingOptions) {
			$optionLines = explode(PHP_EOL, $this->commentsField->ratingOptions);
			foreach ($optionLines as $optionLine) {
				$values = explode("=", $optionLine);
				$options[$values[0]] = $values[1];
			}
		} else {
			$options = array(
				1 => $this->_("Poor"),
				2 => $this->_("Ok"),
				3 => $this->_("Good*"),
				4 => $this->_("Very Good"),
				5 => $this->_("Excellent")
				);
		}

		return $options;
	}

	/**
	 * Render the CommentForm output and process the input if it's been submitted
	 *
	 * @return string
	 *
	 */
	public function render() {

		// Get parsed options array from field settings
		$this->optionsArray = $this->getOptionsArray();

		if(!$this->commentsField) return "Unable to determine comments field";
		$options = $this->options; 	
		$labels = $options['labels'];
		$attrs = $options['attrs'];
		$id = $attrs['id'];
		$submitKey = $id . "_submit";
		$inputValues = array('cite' => '', 'email' => '', 'website' => '', 'text' => ''); 
		$user = wire('user'); 
		if($user->isLoggedin()) {
			$inputValues['cite'] = $user->name; 
			$inputValues['email'] = $user->email;
		}
		$input = $this->fuel('input'); 
		$divClass = 'new';
		$class = $attrs['class'] ? " class='$attrs[class]'" : '';
		$note = '';

		if(is_array($this->session->CommentForm)) {
			// submission data available in the session
			$sessionValues = $this->session->CommentForm;
			foreach($inputValues as $key => $value) {
				if($key == 'text') continue; 
				if(!isset($sessionValues[$key])) $sessionValues[$key] = '';
				$inputValues[$key] = htmlentities($sessionValues[$key], ENT_QUOTES, $this->options['encoding']); 
			}
			unset($sessionValues);
		}

		$showForm = true; 
		if($options['processInput'] && $input->post->$submitKey == 1) {
			if($this->processInput()) {
				$this->processRatingInput();
				return $this->renderSuccess(); // success, return	
			} 
			$inputValues = array_merge($inputValues, $this->inputValues); 
			foreach($inputValues as $key => $value) {
				$inputValues[$key] = htmlentities($value, ENT_QUOTES, $this->options['encoding']); 
			}
			$note = "\n\t$options[errorMessage]";
			$divClass = 'error';

		} else if($this->options['redirectAfterPost'] && $input->get('comment_success') == 1) {
			$note = $this->renderSuccess();
			$showForm = false;
		}

		$form = '';
		if($showForm) {
			$form = "\n<form id='{$id}_form'$class action='$attrs[action]#$id' method='$attrs[method]'>" . 
				"\n\t<p class='{$id}_cite'>" . 
				"\n\t\t<label for='{$id}_cite'>$labels[cite]</label>" . 
				"\n\t\t<input type='text' name='cite' class='required' required='required' id='{$id}_cite' value='$inputValues[cite]' maxlength='128' />" . 
				"\n\t</p>" . 
				"\n\t<p class='{$id}_email'>" . 
				"\n\t\t<label for='{$id}_email'>$labels[email]</label>" . 
				"\n\t\t<input type='text' name='email' class='required email' required='required' id='{$id}_email' value='$inputValues[email]' maxlength='255' />" . 
				"\n\t</p>";

			if($this->commentsField && $this->commentsField->useWebsite && $this->commentsField->schemaVersion > 0) {
				$form .= 
				"\n\t<p class='{$id}_website'>" . 
				"\n\t\t<label for='{$id}_website'>$labels[website]</label>" . 
				"\n\t\t<input type='text' name='website' class='website' id='{$id}_website' value='$inputValues[website]' maxlength='255' />" . 
				"\n\t</p>";
			}

			if($this->commentsField && $this->commentsField->useRatings) {

				

				$form .= 
				"\n\t<p class='{$id}_rating'>" . 
				"\n\t\t<label for='{$id}_rating'>". $this->_("Rating") ."</label>" . 
				"\n\t\t<select name='rating' class='rating' id='{$id}_rating'>" ;

				foreach ($this->optionsArray as $key => $value) {
					$key = (int) $key;
					$selected = '';
					if (strpos($value, "*")) {
						$selected = "selected='selected'";
						$value = str_replace("*", "", $value);
					}
					$form .= "<option $selected value='$key'>$value</option>";
				}

				$form .= "</select>" . 
				"\n\t</p>";
			}

			$form .="\n\t<p class='{$id}_text'>" . 
				"\n\t\t<label for='{$id}_text'>$labels[text]</label>" . 
				"\n\t\t<textarea name='text' class='required' required='required' id='{$id}_text' rows='$attrs[rows]' cols='$attrs[cols]'>$inputValues[text]</textarea>" . 
				"\n\t</p>" . 
				"\n\t<p class='{$id}_submit'>" . 
				"\n\t\t<button type='submit' name='{$id}_submit' id='{$id}_submit' value='1'>$labels[submit]</button>" . 
				"\n\t\t<input type='hidden' name='page_id' value='{$this->page->id}' />" . 
				"\n\t</p>" . 
				"\n</form>";
		}

		$out = 	"\n<div id='{$id}' class='{$id}_$divClass'>" . 	
			"\n" . $this->options['headline'] . $note . $form . 
			"\n</div><!--/$id-->";


		return $out; 
	
	}

	public function processRatingInput() {
		// TODO: Is there better way to get latest comment id? $this->postedComment doesn't have ID yet for some reason?
		$comment = $this->postedComment;
		$tablename = $this->commentsField->getTable();
		$result = $this->db->query("SELECT id FROM ". $tablename ." WHERE cite = '{$comment->cite}' AND created = '{$comment->created}'");
		while ($row = $result->fetch_assoc()) {
	    	$comment_id = $row['id'];
	    }

	    // Check that the rating is valid based on possible options (so that there is no possible to give 100 rating if 5 is maximum)
	    $validRating = false;
	    $rating = (int) $this->input->post->rating;
	    foreach ($this->optionsArray as $key => $value) {
			if ($key == $rating) $validRating = true;
		}
	    
		if ($validRating && $comment_id && $rating) $this->db->query("INSERT INTO CommentRatings SET comment_id = {$comment_id}, rating = {$rating}");
	}

}