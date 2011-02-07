<?php
/**
 * Gets the user to confirm that their ticket details are correct, and sends
 * a validation email if it is required.
 *
 * @package silverstripe-eventmanagement
 */
class EventRegisterFreeConfirmationStep extends MultiFormStep {

	public static $is_final_step = true;

	protected $registration;

	public function getTitle() {
		return 'Confirmation';
	}

	/**
	 * @return EventRegistration
	 */
	public function getRegistration() {
		return $this->registration;
	}

	/**
	 * Returns this step's data merged with the tickets from the previous step.
	 *
	 * @return array
	 */
	public function loadData() {
		$data    = parent::loadData();
		$tickets = $this->getForm()->getSavedStepByClass('EventRegisterTicketsStep');

		$tickets = $tickets->loadData();
		$data['Tickets'] = $tickets['Tickets'];

		return $data;
	}

	public function getFields() {
		$datetime = $this->getForm()->getController()->getDateTime();
		$tickets  = $this->getForm()->getSavedStepByClass('EventRegisterTicketsStep');
		$total    = $tickets->getTotal();

		$table = new EventRegistrationTicketsTableField('Tickets', $datetime);
		$table->setReadonly(true);
		$table->setShowUnavailableTickets(false);
		$table->setShowUnselectedTickets(false);
		$table->setForceTotalRow(true);
		$table->setTotal($total);

		return new FieldSet(
			new LiteralField('ConfirmTicketsNote',
				'<p>Please confirm the tickets you wish to register for:</p>'),
			$table
		);
	}

	/**
	 * This does not actually perform any validation, but just creates the
	 * initial registration object.
	 */
	public function validateStep($data, $form) {
		$form         = $this->getForm();
		$datetime     = $form->getController()->getDateTime();
		$confirmation = $datetime->Event()->RegEmailConfirm;
		$registration = new EventRegistration();

		// If we require email validation for free registrations, then send
		// out the email and mark the registration. Otherwise immediately
		// mark it as valid.
		if ($confirmation) {
			$email   = new Email();
			$config  = SiteConfig::current_site_config();

			$registration->Status = 'Unconfirmed';
			$registration->write();

			if (Member::currentUserID()) {
				$details = array(
					'Name'  => Member::currentUser()->getName(),
					'Email' => Member::currentUser()->Email
				);
			} else {
				$details = $form->getSavedStepByClass('EventRegisterTicketsStep');
				$details = $details->loadData();
			}

			$link = Controller::join_links(
				$this->getForm()->getController()->Link(),
				'confirm', $registration->ID, '?token=' . $registration->Token
			);

			$email->setTo($details['Email']);
			$email->setSubject(sprintf(
				'Confirm Registration For %s (%s)', $datetime->EventTitle(), $config->Title
			));

			$email->setTemplate('EventRegistrationConfirmationEmail');
			$email->populateTemplate(array(
				'Name'        => $details['Name'],
				'Time'        => $datetime,
				'SiteConfig'  => $config,
				'ConfirmLink' => Director::absoluteURL($link)
			));

			$email->send();
		} else {
			$registration->Status = 'Valid';
		}

		$registration->write();
		$this->registration = $registration;

		return true;
	}

}