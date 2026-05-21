<?php

namespace App\Constants;

/**
 * Class TranslationCode
 *
 * @package App\Constants
 */
class TranslationCode
{
    /* Misc */
    const ERROR_APPLICATION = 'errors.application';
    const ERROR_UNAUTHORIZED = 'errors.unauthorized';
    const ERROR_FORBIDDEN = 'errors.forbidden';
    const ERROR_NOT_FOUND = 'errors.notFound';

    /* Register */
    const ERROR_REGISTER_EMAIL_REQUIRED = 'errors.registerEmail.required';
    const ERROR_REGISTER_EMAIL_INVALID = 'errors.registerEmail.invalid';
    const ERROR_REGISTER_EMAIL_REGISTERED = 'errors.registerEmail.registered';
    const ERROR_REGISTER_PASSWORD_REQUIRED = 'errors.registerPassword.required';
    const ERROR_REGISTER_PASSWORD_MIN8 = 'errors.registerPassword.min8';
    const ERROR_REGISTER_PASSWORD_COMPLEXITY = 'errors.registerPassword.complexity';
    const ERROR_REGISTER_RETYPE_PASSWORD_REQUIRED = 'errors.registerRetypePassword.required';
    const ERROR_REGISTER_RETYPE_PASSWORD_SAME = 'errors.registerRetypePassword.same';


    /* Forgot and change password */
    const ERROR_FORGOT_EMAIL_REQUIRED = 'errors.forgotEmail.required';
    const ERROR_FORGOT_EMAIL_INVALID = 'errors.forgotEmail.invalid';
    const ERROR_FORGOT_EMAIL_NOT_REGISTERED = 'errors.forgotEmail.notRegistered';
    const ERROR_FORGOT_CODE_REQUIRED = 'errors.forgotCode.required';
    const ERROR_FORGOT_PASSWORD_REQUIRED = 'errors.forgotPassword.required';
    const ERROR_FORGOT_PASSWORD_MIN8 = 'errors.forgotPassword.min8';
    const ERROR_FORGOT_PASSWORD_COMPLEXITY = 'errors.forgotPassword.complexity';
    const ERROR_FORGOT_RETYPE_PASSWORD_REQUIRED = 'errors.forgotRetypePassword.required';
    const ERROR_FORGOT_RETYPE_PASSWORD_SAME = 'errors.forgotRetypePassword.same';
    const ERROR_FORGOT_ACCOUNT_UNACTIVATED = 'errors.forgotAccount.notActivated';
    const ERROR_FORGOT_CODE_SEND_COOLDOWN = 'errors.forgotCode.sendCooldown';
    const ERROR_FORGOT_CODE_INVALID = 'errors.forgotCode.invalid';
    const ERROR_FORGOT_PASSED_1H = 'errors.forgotCode.passed1H';

    /* Login */
    const ERROR_EMAIL_REQUIRED = 'errors.email.required';
    const ERROR_EMAIL_INVALID = 'errors.email.invalid';
    const ERROR_EMAIL_NOT_REGISTERED = 'errors.email.notRegistered';
    const ERROR_PASSWORD_REQUIRED = 'errors.password.required';
    const ERROR_CREDENTIALS_INVALID = 'errors.credentials.invalid';

    /* Update profile */
    const ERROR_UPDATE_OLD_PASSWORD_REQUIRED = 'errors.updateNewPassword.requiredOldPassword';
    const ERROR_UPDATE_NEW_PASSWORD_MIN8 = 'errors.updateNewPassword.min8';
    const ERROR_UPDATE_NEW_PASSWORD_COMPLEXITY = 'errors.updateNewPassword.complexity';
    const ERROR_UPDATE_RETYPE_PASSWORD_REQUIRED = 'errors.updateNewPassword.required';
    const ERROR_UPDATE_RETYPE_PASSWORD_SAME = 'errors.updateNewPassword.same';
    const ERROR_UPDATE_LANGUAGE_REQUIRED = 'errors.updateLanguage.required';
    const ERROR_UPDATE_LANGUAGE_EXISTS = 'errors.updateLanguage.notExists';
    const ERROR_UPDATE_OLD_PASSWORD_WRONG = 'errors.updateOldPassword.wrong';
}