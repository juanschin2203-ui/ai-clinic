<x-mail::message>
# Reset your password

Hi {{ $user->name }},

We received a request to reset the password for your Rocket Coding account.
If you made this request, click the button below. If you didn't, you can
safely ignore this email — your password will not change.

<x-mail::button :url="$resetUrl">
Reset Password
</x-mail::button>

This link expires in {{ $ttlMinutes }} minutes for security.

Thanks,
The Rocket Coding Team
</x-mail::message>
