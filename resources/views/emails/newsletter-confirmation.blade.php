<h1>Confirmă abonarea</h1>
<p>Ai cerut abonarea adresei {{ $subscriber->email }} la newsletterul Novelion.</p>
<p><a href="{{ route('newsletter.confirm', $subscriber->confirmation_token) }}">Confirmă abonarea</a></p>
<p>Abonarea devine activă numai după accesarea linkului. Dacă nu ai făcut cererea, ignoră mesajul.</p>
<p>NOVELION S.R.L. · novelionprime@gmail.com · 0750 444 672</p>
