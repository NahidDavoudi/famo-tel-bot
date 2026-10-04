<?php
declare(strict_types=1);

namespace App\Telegram;

final class KeyboardKit
{
    public const CB_NOP = 'nop';
    public const CB_HOME = 'h';
    public const CB_CHECK = 'ln:check';
    public const CB_ACCOUNT = 'ac';
    public const CB_ROLE_STUDENT = 'ac:role:student';
    public const CB_ROLE_SUPPORTER = 'ac:role:supporter';
    public const CB_SIGNUP_CANCEL = 'ln:cancel';
    public const CB_GRADE = 'grade:';
    public const CB_MAJOR = 'major:';
    public const CB_SWITCH = 'ac:switch';
    public const CB_UNLINK = 'ac:unlink';
    public const CB_UNLINK_OK = 'ac:unlink:ok';
    public const CB_AC_HOME = 'ac:home';
    public const CB_TODAY = 'st:t';
    public const CB_WEEK = 'st:w';
    public const CB_WEEK_AT = 'st:w:';
    public const CB_DAY = 'st:d:';
    public const CB_FILES = 'st:f:';
    public const CB_NEW = 'st:n';

    // Supporter
    public const CB_SUPPORTER_HOME = 'sp';
    public const CB_INBOX_AT = 'sp:inbox:';
    public const CB_STUDENTS = 'sp:students';
    public const CB_STUDENT = 'sp:stu:';
    public const CB_SUPPORTER_DAY = 'sp:day:';
    public const CB_SUPPORTER_FILES = 'sp:f:';
    public const CB_REPLY = 'sp:reply:';

    // Broadcast
    public const CB_BROADCAST = 'bc';
    public const CB_BC_AUDIENCE = 'bc:a:';
    public const CB_BC_SEND = 'bc:ok';
    public const CB_BC_CANCEL = 'bc:c';
    public const CB_BC_LIST = 'bc:list';
    public const CB_BC_VIEW = 'bc:v:';

    public const LBL_TODAY = 'گفتگوی امروز';
    public const LBL_WEEK = 'وضعیت هفته';
    public const LBL_HOME = 'منوی اصلی';
    public const LBL_INBOX = 'صندوق ورودی';
    public const LBL_STUDENTS = 'دانشجوها';
    public const LBL_BROADCAST = 'پیام همگانی';

    public const ROUTE_TODAY = 'today';
    public const ROUTE_WEEK = 'week';
    public const ROUTE_HOME = 'home';
    public const ROUTE_INBOX = 'inbox';
    public const ROUTE_STUDENTS = 'students';
    public const ROUTE_BROADCAST = 'broadcast';

    /**  list<list<array<string,string>>> ReplyKeyboardMarkup only (text buttons, no callback_data) */
    public static function replyKeyboardStudentMenu(): array
    {
        return [
            [self::key(self::LBL_TODAY), self::key(self::LBL_WEEK)],
            [self::key(self::LBL_HOME)],
        ];
    }

    /**  list<list<array<string,string>>> ReplyKeyboardMarkup only (text buttons, no callback_data) */
    public static function replyKeyboardSupporterMenu(): array
    {
        return [
            [self::key(self::LBL_INBOX), self::key(self::LBL_STUDENTS)],
            [self::key(self::LBL_BROADCAST), self::key(self::LBL_HOME)],
        ];
    }

    /** @return list<list<array{text:string,request_contact?:bool}>> */
    public static function replyKeyboardRequestContact(): array
    {
        return [[['text' => '📱 ارسال شماره', 'request_contact' => true]]];
    }

    /** @return list<list<array{text:string,callback_data:string}>> */
    public static function signupGrades(): array
    {
        $rows = [
            [self::btn((string) 7, self::CB_GRADE . 7) , self::btn((string) 8, self::CB_GRADE . 8) , self::btn((string) 9, self::CB_GRADE . 9)],
            [self::btn((string) 10, self::CB_GRADE . 10) , self::btn((string) 11, self::CB_GRADE . 11) , self::btn((string) 12, self::CB_GRADE . 12)]
        ];
        
        $rows[] = [self::btn('انصراف', self::CB_SIGNUP_CANCEL)];

        return $rows;
    }

    /** @return list<list<array{text:string,callback_data:string}>> */
    public static function signupMajors(): array
    {
        return [
            [self::btn('تجربی', self::CB_MAJOR . 'tajrobi') , self::btn('ریاضی', self::CB_MAJOR . 'riazi')],
            [self::btn('انسانی', self::CB_MAJOR . 'ensani') , self::btn('انصراف', self::CB_SIGNUP_CANCEL)],
        ];
    }

    /** @return list<list<array{text:string,callback_data:string}>> */
    public static function signupCancel(): array
    {
        return [[self::btn('انصراف', self::CB_SIGNUP_CANCEL)]];
    }

    public static function labelRoute(string $label): ?string
    {
        return match (trim($label)) {
            self::LBL_TODAY => self::ROUTE_TODAY,
            self::LBL_WEEK => self::ROUTE_WEEK,
            self::LBL_HOME => self::ROUTE_HOME,
            self::LBL_INBOX => self::ROUTE_INBOX,
            self::LBL_STUDENTS => self::ROUTE_STUDENTS,
            self::LBL_BROADCAST => self::ROUTE_BROADCAST,
            default => null,
        };
    }

    /**  array{text:string,callback_data:string} */
    public static function btn(string $text, string $callback): array
    {
        return ['text' => $text, 'callback_data' => $callback];
    }

    /**  array{text:string,url:string} */
    public static function urlBtn(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**  array{text:string} */
    private static function key(string $label): array
    {
        return ['text' => $label];
    }
}
