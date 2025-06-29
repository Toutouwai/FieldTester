<?php namespace ProcessWire;

class FieldTester extends WireData implements Module {

	/**
	 * Ready
	 */
	public function ready() {
		$this->addHookAfter('ProcessField::buildEditFormBasics', $this, 'modifyEditForm');
		$this->addHookBefore('ProcessField::executeSave', $this, 'beforeFieldSave');
	}

	/**
	 * After ProcessField::buildEditFormBasics
	 *
	 * @param HookEvent $event
	 */
	protected function modifyEditForm(HookEvent $event) {
		/** @var ProcessField $process */
		$process = $event->object;
		/** @var InputfieldForm $form  */
		$form = $event->return;
		$field = $process->getField();
		if(!$field) return;
		$modules = $this->wire()->modules;

		// Check if a testing template and page exist
		$t = $this->getTestTemplate($field);
		$p = $this->getTestPage($field);
		// Add form fields accordingly
		if($t && $p->id) {

			// Add fieldset
			/** @var InputfieldFieldset $fs */
			$fs = $modules->get('InputfieldFieldset');
			$fs->label = 'FieldTester';
			$fs->icon = 'stethoscope';
			$fs->collapsed = Inputfield::collapsedYes;
			$form->add($fs);

			// Add markup field with link
			/** @var InputfieldMarkup $f */
			$f = $modules->get('InputfieldMarkup');
			$f->label = $this->_('Visit testing page');
			$link_text = $p->getFormatted('title');
			$f->value = <<<EOT
<a href="$p->editUrl">$link_text</a>
EOT;
			$fs->add($f);

			// Add checkbox field for deleting template and page
			/** @var InputfieldCheckbox $f */
			$f = $modules->get('InputfieldCheckbox');
			$f->name = 'deleteTestTemplateAndPage';
			$f->label = $this->_('Permanently delete template and page used for field testing');
			$f->label2 = $this->_('Permanently delete template and page');
			$f->notes = $this->_('Tick the checkbox and save the field to delete the testing template and page.');
			$fs->add($f);

		} else {

			// Add checkbox field for adding template and page
			/** @var InputfieldCheckbox $f */
			$f = $modules->get('InputfieldCheckbox');
			$f->name = 'createTestTemplateAndPage';
			$f->label = 'FieldTester: ' . $this->_('Create template and page');
			$f->label2 = $this->_('Create template and page for field testing');
			$f->notes = $this->_('Tick the checkbox and save the field to create the testing template and page. You will be redirected to edit the page.');
			$f->icon = 'stethoscope';
			$f->collapsed = Inputfield::collapsedYes;
			$form->add($f);
		}
	}

	/**
	 * Before ProcessField::executeSave
	 * This needs to be attached to a "before" hook because the hooked method does a redirect
	 * so an "after" hook does not get triggered
	 *
	 * @param HookEvent $event
	 */
	protected function beforeFieldSave(HookEvent $event) {
		/** @var ProcessField $process */
		$process = $event->object;
		$templates = $this->wire()->templates;
		$pages = $this->wire()->pages;
		$input = $this->wire()->input;
		$session = $this->wire()->session;
		$field = $process->getField();
		if(!$field) return;

		// If "create" checkbox field was checked
		if($input->post('createTestTemplateAndPage')) {

			// Create template
			$template_name = 'field_tester_' . $field->name;
			$t = $templates->get($template_name);
			if(!$t) {
				/** @var Fieldgroup $fg */
				$fg = $this->wire(new Fieldgroup());
				$fg->name = $template_name;
				$fg->add('title');
				$fg->add($field);
				$fg->save();
				/** @var Template $t */
				$t = $this->wire(new Template());
				$t->name = $template_name;
				$t->label = 'FieldTester: ' . $field->label;
				$t->fieldgroup = $fg;
				$t->noChildren = 1;
				$t->noParents = -1; // Only one page allowed
				$t->save();
			}

			// Create page
			$p = $this->getTestPage($field);
			if(!$p->id) {
				$title = 'FieldTester: ' . $field->label;
				$page_name = $this->wire()->sanitizer->pageNameTranslate($title);
				$p = $pages->newPage([
					'template' => $t,
					'parent' => '/',
					'title' => $title,
					'name' => $page_name,
				]);
				$p->addStatus(Page::statusHidden);
				$p->save();
			}

			// Redirect to edit page
			$session->location($p->editUrl);
		}

		// If "delete" checkbox field was checked
		if($input->post('deleteTestTemplateAndPage')) {
			// Delete page and template
			$t = $this->getTestTemplate($field);
			if(!$t) return;
			$p = $this->getTestPage($field);
			if($p->id) $pages->delete($p);
			$templates->delete($t);
		}
	}


	/**
	 * Get the test template created by this module
	 *
	 * @param Field $field
	 * @return Template|null
	 */
	protected function getTestTemplate(Field $field) {
		return $this->wire()->templates->get('field_tester_' . $field->name);
	}

	/**
	 * Get the test page created by this module
	 *
	 * @param Field $field
	 * @return Page|NullPage
	 */
	protected function getTestPage(Field $field) {
		$t = $this->getTestTemplate($field);
		if(!$t) return new NullPage();
		return $this->wire()->pages->get("parent=1, template=$t");
	}

}
