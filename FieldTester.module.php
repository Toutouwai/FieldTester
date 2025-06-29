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

		// Check if there is a template named the same as the field
		$t = $this->wire()->templates->get($field->name);
		if($t) {
			// Check if there is a page named after the field label
			$page_name = $this->wire()->sanitizer->pageNameTranslate($field->label);
			$p = $this->wire()->pages->get("parent=1, name=$page_name");
			if($p->id) {
				// Add markup field with link
				/** @var InputfieldMarkup $f */
				$f = $modules->get('InputfieldMarkup');
				$f->label = $this->_('Visit testing page');
				$link_text = $p->getFormatted('title');
				$f->value = <<<EOT
<a href="$p->editUrl">$link_text</a>
EOT;
				$f->icon = 'stethoscope';
				$f->collapsed = Inputfield::collapsedYes;
				$form->add($f);
			}
		} else {
			// Add checkbox field
			/** @var InputfieldCheckbox $f */
			$f = $modules->get('InputfieldCheckbox');
			$f->name = 'createTestTemplateAndPage';
			$f->label = $this->_('Create template and page for field testing');
			$f->label2 = $this->_('Create template and page');
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
		$pages = $this->wire()->pages;
		$field = $process->getField();
		if(!$field) return;

		// Return early if checkbox field was not checked
		$create = $this->wire()->input->post('createTestTemplateAndPage');
		if(!$create) return;

		// Create template
		$t = $this->wire()->templates->get($field->name);
		if($t) return;
		/** @var Fieldgroup $fg */
		$fg = $this->wire(new Fieldgroup());
		$fg->name = $field->name;
		$fg->add('title');
		$fg->add($field);
		$fg->save();
		/** @var Template $t */
		$t = $this->wire(new Template());
		$t->name = $field->name;
		$t->label = $field->label;
		$t->fieldgroup = $fg;
		$t->noChildren = 1;
		$t->noParents = -1; // Only one page allowed
		$t->save();

		// Create page
		$page_name = $this->wire()->sanitizer->pageNameTranslate($field->label);
		$p = $pages->get("parent=1, name=$page_name, template=$t");
		if($p->id) return;
		$p = $pages->newPage([
			'template' => $t,
			'parent' => '/',
			'title' => $field->label,
			'name' => $page_name,
		]);
		$p->addStatus(Page::statusHidden);
		$p->save();

		// Redirect to edit page
		$this->wire()->session->location($p->editUrl);
	}

}
