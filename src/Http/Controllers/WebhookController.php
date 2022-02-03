<?php
namespace Techjeed\Cashier\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Techjeed\Cashier\Cashier;
use Illuminate\Support\Carbon;
use Illuminate\Routing\Controller;
use Techjeed\Cashier\Subscription;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Techjeed\Cashier\Http\Middleware\VerifyWebhookSignature;

class WebhookController extends Controller
{
    /**
     * Create a new webhook controller instance.
     *
     * @return voCode
     */
    // public function __construct()
    // {
    //     if (config('paystack.secretKey')) {
    //         $this->middleware(VerifyWebhookSignature::class);
    //     }
    // }
    /**
     * Handle a Paystack webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['event']));

        if (method_exists($this, $method)) {

            return $this->{$method}($payload);
        }
        return $this->missingMethod();
    }
    /**
     * Handle customer subscription create.
     *
     * @param  array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCreate(array $payload)
    {
        $data = $payload['data'];
        $user = $this->getUserByPaystackCode($data['customer']['customer_code']);
        $subscription = $this->getSubscriptionByCode($data['subscription_code']);

        Subscription::create([
            'user_id' => 1,
            'name' => $data['customer']['customer_code'],
            'paystack_id'   => $data['subscription_code'],
            'paystack_code' => 121212,
            'paystack_plan' => 223,
            'quantity' => 1,
            'trial_ends_at' => Carbon::now(),
            'ends_at' => null,
        ]);

        if ($user && !isset($subscription)) {
            $plan = $data['plan'];
            $subscription = $user->newSubscription($plan['name'], $plan['plan_code']);
            $data['id'] =  null;
            $subscription->add($data);
        }
        return new Response('Webhook Handled', 200);
    }
    /**
     * Handle a subscription disabled notification from paystack.
     *
     * @param  array $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionDisable($payload)
    {
        return $this->cancelSubscription($payload['data']['subscription_code']);
    }
    /**
     * Handle a subscription cancellation notification from paystack.
     *
     * @param  string  $subscriptionCode
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function cancelSubscription($subscriptionCode)
    {
        $subscription = $this->getSubscriptionByCode($subscriptionCode);
        if ($subscription && (! $subscription->cancelled() || $subscription->onGracePeriod())) {
            $subscription->markAsCancelled();
        }
        return new Response('Webhook Handled', 200);
    }
    /**
     * Get the model for the given subscription Code.
     *
     * @param  string  $subscriptionCode
     * @return \Techjeed\Cashier\Subscription|null
     */
    protected function getSubscriptionByCode($subscriptionCode): ?Subscription
    {
        return Subscription::where('paystack_code', $subscriptionCode)->first();
    }
    /**
     * Get the billable entity instance by Paystack Code.
     *
     * @param  string  $paystackCode
     * @return \Techjeed\Cashier\Billable
     */
    protected function getUserByPaystackCode($paystackCode)
    {
        $model = Cashier::paystackModel();
        return (new $model)->where('paystack_code', $paystackCode)->first();
    }
    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function missingMethod($parameters = [])
    {
        return new Response;
    }
}
