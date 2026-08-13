@if($canCreateInvoice || $canRecordPayment)
    @php
        $reopenAddRecordModal = $errors->any() && ! is_null(old('transaction_date'));
        $initialFinanceType = old('type', $allowedEntryTypes[0] ?? 'invoice');
        $initialPaymentPlan = old('payment_plan', $selectedStudent->preferred_payment_method === 'semester' ? 'semester' : 'full');
        $initialSemesterIds = collect(old('semester_ids', []))->map(fn ($id) => (string) $id)->values();
        $initialAmountRaw = old('amount', '');
        $initialAmount = $initialAmountRaw !== ''
            ? rtrim(rtrim(number_format((float) $initialAmountRaw, 2, '.', ','), '0'), '.')
            : '';
    @endphp
    <x-modal name="add-finance-record" max-width="2xl" focusable :show="$reopenAddRecordModal">
        <div
            x-data="{
                recordType: @js($initialFinanceType),
                paymentPlan: @js($initialPaymentPlan),
                amount: @js($initialAmount),
                currency: @js(old('currency', 'IQD')),
                selectedSemesters: @js($initialSemesterIds),
                installmentCount: @js((string) old('installment_count', $expectedInstallmentCount)),
                discountMode: @js(old('discount_mode', 'amount')),
                discountPercentage: @js(old('discount_percentage', '')),
                selectedInvoiceId: @js((string) old('invoice_transaction_id', '')),
                invoiceAmounts: @js($invoiceOptions->mapWithKeys(fn ($invoice) => [(string) $invoice->id => (float) $invoice->amount])),
                formattedAmount() {
                    const amount = Number.parseFloat(String(this.amount || 0).replace(/,/g, ''));
                    const decimals = this.currency === 'USD' ? 2 : 0;

                    return formatMoneyDisplay((Number.isFinite(amount) ? amount : 0).toFixed(decimals));
                },
                perSemesterAmount() {
                    const amount = Number.parseFloat(String(this.amount || 0).replace(/,/g, ''));
                    const count = Number.parseInt(this.installmentCount || 0, 10) || this.selectedSemesters.length;
                    const decimals = this.currency === 'USD' ? 2 : 0;

                    return formatMoneyDisplay((count > 0 ? amount / count : 0).toFixed(decimals));
                },
                discountPercentageAmount() {
                    const invoiceAmount = Number(this.invoiceAmounts[this.selectedInvoiceId] || 0);
                    const pct = Number.parseFloat(this.discountPercentage || 0);
                    const decimals = this.currency === 'USD' ? 2 : 0;
                    const computed = Number.isFinite(invoiceAmount) && Number.isFinite(pct) ? (invoiceAmount * pct) / 100 : 0;

                    return formatMoneyDisplay(computed.toFixed(decimals));
                }
            }"
            class="p-6"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Add Finance Record</h2>
                <button type="button" x-on:click="$dispatch('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">&times;</button>
            </div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Invoice and receipt numbers are generated automatically unless you enter one.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('finance.transactions.store') }}" class="mt-5 grid grid-cols-1 gap-4" x-on:submit="stripMoneyCommas($el)">
                @csrf
                <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">

                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                    <select name="type" x-model="recordType" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                        @foreach($types as $value => $label)
                            @if(in_array($value, $allowedEntryTypes, true))
                                <option value="{{ $value }}" @selected($initialFinanceType === $value)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                    @if($autoBilledPlan)
                        <p class="mt-2 text-xs text-blue-800 dark:text-blue-300">This student is on an automatically billed tuition plan — invoices are generated automatically at the department's rate. Manual invoice entry is disabled here to prevent conflicting charges.</p>
                    @endif
                </div>

                <div class="min-w-0" x-show="recordType === 'discount'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Type</label>
                    <select name="discount_mode" x-model="discountMode" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="amount" @selected(old('discount_mode', 'amount') === 'amount')>Fixed amount</option>
                        <option value="percentage" @selected(old('discount_mode') === 'percentage')>Percentage of invoice</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0" x-show="!(recordType === 'discount' && discountMode === 'percentage')">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            <span x-show="recordType === 'invoice'">Agreed Tuition Charge</span>
                            <span x-show="recordType !== 'invoice'">Amount</span>
                        </label>
                        <input type="text" inputmode="decimal" name="amount" value="{{ $initialAmount }}" x-on:input="formatMoneyInput($event); amount = $event.target.value" class="money-input mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" :required="!(recordType === 'discount' && discountMode === 'percentage')">
                    </div>

                    <div class="min-w-0" x-show="recordType === 'discount' && discountMode === 'percentage'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Discount Percentage</label>
                        <input type="number" min="0.01" max="100" step="0.01" name="discount_percentage" value="{{ old('discount_percentage') }}" x-model="discountPercentage" placeholder="e.g. 10" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" :required="recordType === 'discount' && discountMode === 'percentage'">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedInvoiceId">&asymp; <span class="font-semibold" x-text="discountPercentageAmount()"></span> <span x-text="currency"></span> off the selected invoice.</p>
                    </div>

                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                        <select name="currency" x-model="currency" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="IQD" @selected(old('currency', 'IQD') === 'IQD')>IQD</option>
                            <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                        </select>
                    </div>
                </div>

                @if($canCreateInvoice)
                    <div x-show="recordType === 'invoice'" class="min-w-0 rounded-md border border-blue-100 bg-blue-50 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                        <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100">Student tuition agreement</label>
                        <p class="mt-1 text-xs text-blue-900 dark:text-blue-200">Enter the agreed tuition charge, choose how the student wants to pay it, and the system will create the correct invoice schedule.</p>
                        @if($selectedStudent->preferred_payment_method === 'per_credit')
                            <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">The registrar recorded this student for per-credit billing. Use "Generate Tuition Charge" instead so charges are computed automatically at the department's rate.</p>
                        @elseif($selectedStudent->preferred_payment_method)
                            <p class="mt-2 text-xs text-blue-800 dark:text-blue-300">Registrar recorded plan: {{ $selectedStudent->preferred_payment_method === 'semester' ? 'divide tuition by semesters' : 'full tuition paid once' }}{{ $selectedStudent->preferred_installment_count ? ' ('.$selectedStudent->preferred_installment_count.' installments)' : '' }}.</p>
                        @endif
                        <select name="payment_plan" x-model="paymentPlan" class="mt-3 block w-full min-w-0 rounded-md border-blue-200 bg-white text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-blue-800 dark:bg-gray-800 dark:text-gray-100">
                            <option value="full" @selected($initialPaymentPlan === 'full')>Full tuition paid once</option>
                            <option value="semester" @selected($initialPaymentPlan === 'semester')>Divide tuition by semesters</option>
                        </select>

                        <div x-show="paymentPlan === 'full'" class="mt-4 rounded-md border border-blue-200 bg-white px-3 py-2 text-sm text-blue-900 dark:border-blue-800 dark:bg-gray-800 dark:text-blue-200">
                            <p>One tuition invoice will be created for <span class="font-semibold" x-text="formattedAmount()"></span>.</p>
                            @if($canCollectPayment)
                            <label class="mt-3 flex items-start gap-2">
                                <input type="checkbox" name="collect_now" value="1" @checked(old('collect_now')) class="mt-0.5 rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                                <span>
                                    <span class="block font-semibold">Collect the full payment now</span>
                                    <span class="block text-xs">{{ $canPostImmediately ? 'Posts immediately and generates a receipt.' : 'Records a payment for independent approval before posting.' }}</span>
                                </span>
                            </label>
                            @endif
                        </div>

                        <div x-show="paymentPlan === 'semester'" class="mt-4 space-y-3">
                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Number of installments (full program length)</label>
                                <input type="number" min="1" max="24" name="installment_count" x-model="installmentCount" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Defaults to {{ $expectedInstallmentCount }} for this student's program. The total tuition is divided across this many semesters, even ones that don't exist yet.</p>
                            </div>

                            <div class="rounded-md border border-blue-200 bg-white px-3 py-2 text-sm text-blue-900 dark:border-blue-800 dark:bg-gray-800 dark:text-blue-200">
                                Each installment is
                                <span class="font-semibold" x-text="perSemesterAmount()"></span>.
                                <span x-show="selectedSemesters.length > 0">
                                    <span class="font-semibold" x-text="selectedSemesters.length"></span> invoice(s) will be created now for the semesters checked below;
                                </span>
                                the remaining installments will be invoiced automatically as future semesters are created.
                            </div>

                            <div class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2">
                                @forelse($semesterOptions as $semester)
                                    <label class="flex min-w-0 items-start gap-2 rounded-md border border-blue-100 bg-white p-3 text-sm dark:border-blue-900 dark:bg-gray-800 {{ $semester->end_date ? '' : 'opacity-60' }}">
                                        <input
                                            type="checkbox"
                                            name="semester_ids[]"
                                            value="{{ $semester->id }}"
                                            x-model="selectedSemesters"
                                            @disabled(! $semester->end_date)
                                            @checked(in_array((string) $semester->id, old('semester_ids', []), true))
                                            class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $semester->name }} {{ $semester->academic_year }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $semester->end_date ? 'Due '.\Illuminate\Support\Carbon::parse($semester->end_date)->format('Y-m-d') : 'Missing semester end date' }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <span class="text-sm text-blue-900 dark:text-blue-200">No semesters are defined for this student's university yet.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                <div class="min-w-0" x-show="recordType !== 'invoice'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" :disabled="recordType === 'invoice'" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                        @foreach(($creationStatuses ?? $statuses) as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="status" value="pending" :disabled="recordType !== 'invoice'">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Transaction Date</label>
                        <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                    </div>
                    <div x-show="recordType !== 'invoice' || paymentPlan !== 'semester'" class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Due Date</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Receipt, invoice, voucher..." class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Academic Year</label>
                        <select name="academic_year_id" :required="recordType === 'invoice'" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">Select academic year</option>
                            @foreach($academicYearOptions as $academicYear)
                                <option value="{{ $academicYear->id }}" @selected(old('academic_year_id') == $academicYear->id)>{{ $academicYear->name }} / {{ ucfirst($academicYear->status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div x-show="recordType === 'invoice'" class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Number</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" placeholder="Auto for invoices" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </div>

                <div x-show="['payment', 'discount'].includes(recordType)" class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Apply Credit To Invoice</label>
                    <select name="invoice_transaction_id" x-model="selectedInvoiceId" :required="recordType === 'discount' && discountMode === 'percentage'" class="mt-1 block w-full min-w-0 truncate rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">No invoice allocation</option>
                        @foreach($invoiceOptions as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('invoice_transaction_id') == $invoice->id)>
                                {{ $invoice->documentNumber() }} / {{ money($invoice->remaining_amount ?? $invoice->amount, $invoice->currency) }} {{ $invoice->currency }} remaining / {{ ucfirst($invoice->payment_status) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="!(recordType === 'discount' && discountMode === 'percentage')">Used for payments and discounts. Currency and student must match the invoice.</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="recordType === 'discount' && discountMode === 'percentage'">Required for percentage discounts — the percentage is applied to this invoice's full amount.</p>
                </div>

                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <textarea name="notes" rows="3" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">{{ old('notes') }}</textarea>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800">
                    <button type="button" x-on:click="$dispatch('close')" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        Cancel
                    </button>
                    <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-500">
                        Save Finance Record
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
@endif
