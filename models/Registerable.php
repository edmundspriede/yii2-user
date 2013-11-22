<?php namespace dektrium\user\models;

use yii\helpers\Security;

/**
 * Registerable is responsible to register user accounts and to send emails with confirmation instructions.
 *
 * @property boolean $isConfirmed
 * @property boolean $isConfirmationPeriodExpired
 *
 * @author Dmitry Erofeev <dmeroff@gmail.com>
 */
trait Registerable
{
    /**
     * Registers a user. If dektrium\user\Module.trackable is enabled, ip address will be saved as "registration_ip".
     * If dektrium\user\Module.confirmable is enabled, user will receive confirmation message.
     *
     * @return bool
     * @throws \RuntimeException Whether user is already created.
     */
    public function register()
    {
        if (!$this->isNewRecord) {
            throw new \RuntimeException('You can not call "register" method on created user.');
        }
        if (\Yii::$app->controller->module->trackable) {
            $this->setAttribute('registration_ip', ip2long(\Yii::$app->getRequest()->getUserIP()));
        }
        if ($this->save()) {
            if (\Yii::$app->controller->module->confirmable) {
                $this->sendConfirmationMessage();
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Confirms the user.
     *
     * @return bool
     * @throws \RuntimeException Whether dektrium\user\Module.confirmable is false.
     */
    public function confirm()
    {
        if (!\Yii::$app->controller->module->confirmable) {
            throw new \RuntimeException('You must enable dektrium\user\Module.confirmable to use method "confirm".');
        } elseif ($this->getIsConfirmed()) {
            return true;
        } elseif ($this->getIsConfirmationPeriodExpired()) {
            return false;
        }

        $this->confirmation_token = null;
        $this->confirmation_sent_time = null;
        $this->confirmation_time = time();

        return $this->save(false);
    }

    /**
     * Generates confirmation data.
     */
    public function generateConfirmationData()
    {
        if (!\Yii::$app->controller->module->confirmable) {
            throw new \RuntimeException('You must enable dektrium\user\Module.confirmable to use method "confirm".');
        }
        $this->confirmation_token = Security::generateRandomKey();
        $this->confirmation_sent_time = time();
        $this->confirmation_time = null;
    }

    /**
     * Manually sends confirmation message to user.
     *
     * @throws \RuntimeException Whether dektrium\user\Module.confirmable is false.
     */
    public function sendConfirmationMessage()
    {
        if (!\Yii::$app->controller->module->confirmable) {
            throw new \RuntimeException('You must enable dektrium\user\Module.confirmable to use method "confirm".');
        }
        $this->generateConfirmationData();
        $this->save(false);
        /** @var \yii\mail\BaseMailer $mailer */
        $mailer = \Yii::$app->mail;
        $mailer->compose(\Yii::$app->controller->module->confirmationMessageView, ['user' => $this])
            ->setTo($this->email)
            ->setFrom(\Yii::$app->controller->module->messageSender)
            ->setSubject(\Yii::$app->controller->module->confirmationMessageSubject)
            ->send();
        \Yii::$app->getSession()->setFlash('confirmation_message_sent');
    }

    /**
     * @return string            Confirmation url.
     * @throws \RuntimeException Whether dektrium\user\Module.confirmable is false.
     */
    public function getConfirmationUrl()
    {
        if (!\Yii::$app->controller->module->confirmable) {
            throw new \RuntimeException('You must enable dektrium\user\Module.confirmable to use method "confirm".');
        }

        return \Yii::$app->getUrlManager()->createAbsoluteUrl('/user/registration/confirm', [
            'id' => $this->id,
            'token' => $this->confirmation_token
        ]);
    }

    /**
     * Verifies whether a user is confirmed or not.
     *
     * @return bool
     * @throws \RuntimeException Whether dektrium\user\Module.confirmable is false.
     */
    public function getIsConfirmed()
    {
        if (!\Yii::$app->controller->module->confirmable) {
            throw new \RuntimeException('You must enable dektrium\user\Module.confirmable to use method "confirm".');
        }

        return $this->confirmation_time !== null;
    }

    /**
     * Checks if the user confirmation happens before the token becomes invalid.
     *
     * @return bool
     * @throws \RuntimeException Whether dektrium\user\Module.confirmable is false.
     */
    public function getIsConfirmationPeriodExpired()
    {
        if (!\Yii::$app->controller->module->confirmable) {
            throw new \RuntimeException('You must enable dektrium\user\Module.confirmable to use method "confirm".');
        }

        return ($this->confirmation_sent_time + \Yii::$app->controller->module->confirmWithin) < time();
    }
}