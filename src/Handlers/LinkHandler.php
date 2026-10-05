<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Famo\ErrorMap;
use App\Famo\FamoApi;
use App\Lang;
use App\Logger;
use App\State\ChatState;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;

final class LinkHandler
{
    public function __construct(
        private readonly FamoApi $api,
        private readonly TelegramApi $tg,
        private readonly ScreenManager $screens,
        private readonly StudentHandler $student,
        private readonly SupporterHandler $supporter,
        private readonly \App\Config $config,
    ) {}

    public function start(ChatState $s, UpdateContext $ctx): void
    {
        $continuingSignup = in_array($s->mode, ['signup_name', 'signup_national_id', 'signup_grade', 'signup_major'], true);
        if (!$continuingSignup) {
            $s->mode = 'idle';
            unset($s->payload['phone'], $s->payload['full_name'], $s->payload['grade'], $s->payload['major']);
        }
        $this->resolve($s, $ctx);
        if ($continuingSignup && $s->role === null && $s->mode === 'idle') {
            $s->mode = 'signup_name';
        }
    }

    public function onCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        match ($data) {
            KeyboardKit::CB_CHECK => $this->resolve($s, $ctx),
            KeyboardKit::CB_SIGNUP_CANCEL => $this->cancelSignup($s, $ctx),
            KeyboardKit::CB_ROLE_STUDENT => $this->activateRole($s, $ctx, 'student'),
            KeyboardKit::CB_ROLE_SUPPORTER => $this->activateRole($s, $ctx, 'supporter'),
            default => $this->signupCallback($s, $data, $ctx),
        };
    }

    public function onContact(ChatState $s, int $contactUserId, string $phone, UpdateContext $ctx): void
    {
        if ($contactUserId !== $ctx->userId) {
            $this->tg->sendMessage($s->chatId, Lang::t('signup.own_phone'));
            return;
        }

        $messageId = $ctx->messageId();
        if ($messageId !== null) {
            $this->tg->deleteMessage($s->chatId, $messageId);
        }
        $this->tg->removeReplyKeyboard($s->chatId, Lang::t('signup.checking_phone'));
        $s->payload['phone'] = $phone;
        $result = $this->api->linkPhone($s->chatId, $phone);
        if (!$result->ok()) {
            $this->handleSignupError($s, $result, 'contact', $ctx);
            return;
        }

        $links = (array) ($result->data()['links'] ?? []);
        if (isset($links[0]) && is_array($links[0])) {
            $this->activate($s, $ctx, $links[0]);
            return;
        }

        $s->role = null;
        $s->mode = 'signup_name';
        $s->payload['phone'] = $phone;
        $this->screens->show($s, new Screen(Lang::t('signup.ask_name'), KeyboardKit::signupCancel()), true);
    }

    public function onSignupText(ChatState $s, string $text, UpdateContext $ctx): void
    {
        if ($s->mode === 'signup_name') {
            $fullName = trim($text);
            if (mb_strlen($fullName) < 2) {
                $this->tg->sendMessage($s->chatId, Lang::t('signup.invalid_name'), KeyboardKit::signupCancel());
                return;
            }

            $s->payload['full_name'] = $fullName;
            $s->mode = 'signup_national_id';
            $this->screens->show($s, new Screen(Lang::t('signup.ask_national_id'), KeyboardKit::signupCancel()), true);
            return;
        }

        if ($s->mode === 'signup_national_id') {
            $nationalId = self::normalizeDigits(trim($text));

            $messageId = $ctx->messageId();
            if ($messageId !== null) {
                $this->tg->deleteMessage($s->chatId, $messageId);
            }

            if (!self::isValidNationalId($nationalId)) {
                $this->tg->sendMessage($s->chatId, Lang::t('signup.invalid_national_id'), KeyboardKit::signupCancel());
                return;
            }

            $s->payload['national_id'] = $nationalId;
            $s->mode = 'signup_grade';
            $this->screens->show($s, new Screen(Lang::t('signup.ask_grade'), KeyboardKit::signupGrades()), true);
        }
    }

    private static function normalizeDigits(string $v): string
    {
        $v = strtr($v, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/\D+/', '', $v) ?? '';
    }

    private static function isValidNationalId(string $id): bool
    {
        if (!preg_match('/^\d{10}$/', $id) || preg_match('/^(\d)\1{9}$/', $id)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $id[$i] * (10 - $i);
        }
        $r = $sum % 11;
        $check = (int) $id[9];

        return $r < 2 ? $check === $r : $check === 11 - $r;
    }

    public function pickRole(ChatState $s, UpdateContext $ctx): void
    {
        $this->resolve($s, $ctx);
    }

    private function resolve(ChatState $s, UpdateContext $ctx): void
    {
        $result = $this->api->resolveByChat($s->chatId);

        if (!$result->ok()) {
            Logger::error('API ERROR', ['error' => $result]);
            if (in_array($result->errorCode, ['BOT_UNAUTHORIZED', 'BOT_NOT_CONFIGURED', 'BOT_IP_FORBIDDEN'], true)) {
                $this->tg->sendMessage($s->chatId, Lang::t('error.system_unavailable'));
                return;
            }

            if (ErrorMap::isTransport($result)) {
                $this->tg->sendMessage($s->chatId, Lang::t('error.connection'));
                return;
            }

            if ($result->status >= 500 || ErrorMap::isDisabled($result)) {
                $this->tg->sendMessage($s->chatId, Lang::t('error.system_unavailable'));
                return;
            }

            if ($result->status === 403) {
                $this->tg->sendMessage($s->chatId, Lang::t('error.system_unavailable'));
                return;
            }

            $this->welcome($s);
            return;
        }

        $links = (array) ($result->data()['links'] ?? []);
        if (isset($links[0]) && is_array($links[0])) {
            $this->activate($s, $ctx, $links[0]);
            return;
        }

        $active = array_values(array_filter(
            $links,
            static fn ($link) => ($link['is_active'] ?? true) !== false
        ));

        if ($active === []) {
            $this->welcome($s);
            return;
        }

        if (count($active) === 1) {
            $s->payload['multi'] = false;
            $this->activate($s, $ctx, $active[0]);
            return;
        }

        $s->payload['multi'] = true;
        $this->chooseRole($s, $active);
    }

    private function welcome(ChatState $s): void
    {
        $this->student->reset($s);
        unset($s->payload['phone'], $s->payload['full_name'], $s->payload['grade'], $s->payload['major']);
        if ($s->activeScreenMessageId !== null) {
            $this->tg->editMessageReplyMarkup($s->chatId, $s->activeScreenMessageId, []);
            $s->activeScreenMessageId = null;
        }
        $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), Lang::t('signup.request_phone'));
    }

    /** @param list<array<string,mixed>> $links */
    private function chooseRole(ChatState $s, array $links): void
    {
        $rows = [];
        foreach ($links as $link) {
            $role = (string) ($link['role'] ?? '');
            $callback = $role === 'student' ? KeyboardKit::CB_ROLE_STUDENT : KeyboardKit::CB_ROLE_SUPPORTER;
            $label = $role === 'student' ? Lang::t('btn.role_student') : Lang::t('btn.role_supporter');
            $rows[] = [KeyboardKit::btn($label, $callback)];
        }

        $this->screens->show($s, new Screen(Lang::t('link.choose_role'), $rows), false);
    }

    private function activateRole(ChatState $s, UpdateContext $ctx, string $role): void
    {
        $result = $this->api->resolveByChat($s->chatId);
        $links = [];
        if ($result->ok()) {
            $links = (array) ($result->data()['links'] ?? []);
        }

        foreach ($links as $link) {
            if (($link['role'] ?? null) === $role) {
                $this->activate($s, $ctx, $link);
                return;
            }
        }

        $this->welcome($s);
    }

    /**
     * @param array<string,mixed> $link
     */
    private function activate(ChatState $s, UpdateContext $ctx, array $link): void
    {
        $role = (string) ($link['role'] ?? '');

        if ($role === 'admin') {
            $role = 'supporter';
        }

        // ← مهم: آیا این کاربر از قبل متصل بوده؟
        $isFirstLink = $s->role === null;

        if ($role === 'supporter') {
            unset($s->payload['phone'], $s->payload['full_name'], $s->payload['grade'], $s->payload['major']);
            $s->role = 'supporter';
            $s->mode = 'idle';
            $s->payload['name'] = (string) ($link['name'] ?? '');
            $s->payload['account_id'] = (int) ($link['account_id'] ?? 0);
            unset($s->payload['supporter']);

            if ($isFirstLink) {
                $messageId = $this->tg->setReplyKeyboard(
                    $s->chatId,
                    KeyboardKit::replyKeyboardSupporterMenu(),
                    Lang::t('link.success_supporter', ['name' => $s->payload['name']])
                );
                $s->activeScreenMessageId = $messageId;
                $this->supporter->home($s, true);
            } else {
                $this->supporter->home($s, false);
            }

            return;
        }

        if ($role !== 'student') {
            $this->welcome($s);
            return;
        }

        unset($s->payload['phone'], $s->payload['full_name'], $s->payload['grade'], $s->payload['major']);
        $s->role = 'student';
        $s->mode = 'idle';
        $s->payload['name'] = (string) ($link['name'] ?? '');
        $s->payload['account_id'] = (int) ($link['account_id'] ?? 0);

        if ($isFirstLink) {
            $messageId = $this->tg->setReplyKeyboard(
                $s->chatId,
                KeyboardKit::replyKeyboardStudentMenu(),
                Lang::t('link.success', ['name' => $s->payload['name']])
            );
            $s->activeScreenMessageId = $messageId;
            $this->student->home($s, $ctx, false);
        } else {
            $this->student->home($s, $ctx, true);
        }
    }

    private function signupCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        if (str_starts_with($data, KeyboardKit::CB_GRADE) && $s->mode === 'signup_grade') {
            $grade = (int) substr($data, strlen(KeyboardKit::CB_GRADE));
            if ($grade < 7 || $grade > 12) {
                return;
            }
            $s->payload['grade'] = $grade;
            if ($grade <= 9) {
                $s->payload['major'] = 'راهنمایی';
                $this->submitRegistration($s, $ctx);
                return;
            }
            $s->mode = 'signup_major';
            $this->screens->show($s, new Screen(Lang::t('signup.ask_major'), KeyboardKit::signupMajors()), true);
            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_MAJOR) && $s->mode === 'signup_major') {
            $major = substr($data, strlen(KeyboardKit::CB_MAJOR));
            if (!in_array($major, ['تجربی', 'ریاضی', 'انسانی'], true)) {
                return;
            }
            $s->payload['major'] = $major;
            $this->submitRegistration($s, $ctx);
        }
    }

    private function submitRegistration(ChatState $s, UpdateContext $ctx): void
    {
        $s->mode = 'signup_submitting';
        $result = $this->api->registerStudent(
            $s->chatId,
            (string) ($s->payload['phone'] ?? ''),
            (string) ($s->payload['full_name'] ?? ''),
            (string) ($s->payload['national_id'] ?? ''),
            (int) ($s->payload['grade'] ?? 0),
            (string) ($s->payload['major'] ?? '')
        );

        if (!$result->ok()) {
            $this->handleSignupError($s, $result, 'register', $ctx);
            return;
        }

        $links = (array) ($result->data()['links'] ?? []);
        if (!isset($links[0]) || !is_array($links[0])) {
            $this->tg->sendMessage($s->chatId, Lang::t('error.generic'));
            $s->mode = 'signup_grade';
            return;
        }

        $s->payload = [];
        $this->activate($s, $ctx, $links[0]);
    }

    private function handleSignupError(ChatState $s, \App\Famo\ApiResult $result, string $step, UpdateContext $ctx): void
    {
        $code = $result->errorCode;
        if ($code === 'VALIDATION_ERROR') {
            if ($step === 'contact') {
                unset($s->payload['phone']);
            }
            $s->mode = match ($step) {
                'contact' => 'idle',
                'register' => isset($s->payload['major']) && $s->payload['major'] !== 'راهنمایی' ? 'signup_major' : 'signup_grade',
                default => $s->mode,
            };
            if ($step === 'contact') {
                $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), Lang::t('signup.request_phone'));
            } else {
                $keyboard = $s->mode === 'signup_major' ? KeyboardKit::signupMajors() : KeyboardKit::signupGrades();
                $this->screens->show($s, new Screen(Lang::t('error.validation'), $keyboard), true);
            }
            if ($step === 'contact') {
                $this->tg->sendMessage($s->chatId, Lang::t('error.validation'));
            }
            return;
        }

        if (in_array($code, ['BOT_UNAUTHORIZED', 'BOT_NOT_CONFIGURED', 'BOT_IP_FORBIDDEN'], true)) {
            if ($step === 'contact') {
                $s->mode = 'idle';
                $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), Lang::t('error.system_unavailable'));
            } else {
                $s->mode = isset($s->payload['major']) && $s->payload['major'] !== 'راهنمایی' ? 'signup_major' : 'signup_grade';
                $keyboard = $s->mode === 'signup_major' ? KeyboardKit::signupMajors() : KeyboardKit::signupGrades();
                $this->screens->show($s, new Screen(Lang::t('error.system_unavailable'), $keyboard), true);
            }
            return;
        }

        if (ErrorMap::isTransport($result)) {
            $s->mode = match ($step) {
                'contact' => 'idle',
                'register' => isset($s->payload['major']) && $s->payload['major'] !== 'راهنمایی' ? 'signup_major' : 'signup_grade',
                default => $s->mode,
            };
            if ($step === 'contact') {
                $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), Lang::t('signup.request_phone'));
                $this->tg->sendMessage($s->chatId, Lang::t('error.connection'));
            } else {
                $keyboard = $s->mode === 'signup_major' ? KeyboardKit::signupMajors() : KeyboardKit::signupGrades();
                $this->screens->show($s, new Screen(Lang::t('error.connection'), $keyboard), true);
            }
            return;
        }

        if ($result->status >= 500) {
            if ($step === 'contact') {
                $s->mode = 'idle';
                $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), Lang::t('error.system_unavailable'));
            } else {
                $s->mode = isset($s->payload['major']) && $s->payload['major'] !== 'راهنمایی' ? 'signup_major' : 'signup_grade';
                $keyboard = $s->mode === 'signup_major' ? KeyboardKit::signupMajors() : KeyboardKit::signupGrades();
                $this->screens->show($s, new Screen(Lang::t('error.system_unavailable'), $keyboard), true);
            }
            return;
        }

        if (in_array($code, ['CHAT_ALREADY_LINKED', 'USER_ALREADY_LINKED', 'PHONE_ALREADY_REGISTERED'], true)) {
            if ($step === 'contact') {
                $s->mode = 'idle';
                unset($s->payload['phone']);
                $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), Lang::t('signup.request_phone'));
                $this->tg->sendMessage($s->chatId, ErrorMap::toPersian($code, $result->status, $result->transportError));
            } else {
                $keyboard = $s->mode === 'signup_major' ? KeyboardKit::signupMajors() : KeyboardKit::signupGrades();
                $this->screens->show($s, new Screen(ErrorMap::toPersian($code, $result->status, $result->transportError), $keyboard), true);
            }
            return;
        }

        if ($step === 'contact') {
            $s->mode = 'idle';
            unset($s->payload['phone']);
            $this->tg->setReplyKeyboard($s->chatId, KeyboardKit::replyKeyboardRequestContact(), ErrorMap::toPersian($code, $result->status, $result->transportError));
            $this->tg->sendMessage($s->chatId, ErrorMap::toPersian($code, $result->status, $result->transportError));
        } elseif ($step === 'register') {
            $s->mode = isset($s->payload['major']) && $s->payload['major'] !== 'راهنمایی' ? 'signup_major' : 'signup_grade';
            $keyboard = $s->mode === 'signup_major' ? KeyboardKit::signupMajors() : KeyboardKit::signupGrades();
            $this->screens->show($s, new Screen(ErrorMap::toPersian($code, $result->status, $result->transportError), $keyboard), true);
        }
    }

    private function cancelSignup(ChatState $s, UpdateContext $ctx): void
    {
        $s->role = null;
        $s->mode = 'idle';
        $s->payload = [];
        $this->resolve($s, $ctx);
    }
}