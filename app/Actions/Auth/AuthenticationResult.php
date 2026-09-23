<?php

namespace App\Actions\Auth;

/**
 * Чем закончилась попытка входа на витрине.
 */
enum AuthenticationResult
{
    case Success;

    /** Нет такой почты или телефона, либо пароль не подошёл — снаружи не различаются. */
    case Failed;

    /** Пароль верный, но вход в кабинет закрыт. */
    case Inactive;
}
