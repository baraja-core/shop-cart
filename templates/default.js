Vue.component("cms-cart-voucher-default", {
  template: `<div class="container-fluid">
	<div class="row mt-2">
		<div class="col">
			<h1>Voucher manager</h1>
		</div>
		<div class="col-3 text-right">
			<button class="btn btn-secondary" v-b-modal.modal-new-voucher>New voucher</button>
		</div>
	</div>
	<div v-if="feed === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<b-card v-else>
		<table class="table table-sm cms-table-no-border-top">
			<tr>
				<th>ID</th>
				<th>Code</th>
				<th>Type</th>
				<th>Value</th>
				<th>Percentage</th>
				<th>Usage limit</th>
				<th>Used count</th>
				<th>Active</th>
				<th>Must be unique</th>
				<th>Note</th>
				<th>Valid</th>
				<th>Inserted</th>
				<th>Used</th>
			</tr>
			<tr v-for="voucher in feed">
				<td>{{ voucher.id }}</td>
				<td><code>{{ voucher.code }}</code></td>
				<td>{{ voucher.type }}</td>
				<td>{{ voucher.value }}</td>
				<td>{{ voucher.percentage ?? '-' }}</td>
				<td>{{ voucher.usageLimit }}</td>
				<td>{{ voucher.usedCount }}</td>
				<td>{{ voucher.active ? 'yes' : 'no' }}</td>
				<td>{{ voucher.mustBeUnique ? 'yes' : 'no' }}</td>
				<td>{{ voucher.note ?? '-' }}</td>
				<td>{{ voucher.validFrom ?? '?' }} - {{ voucher.validTo ?? '?' }}</td>
				<td>{{ voucher.insertedDate }}</td>
				<td>{{ voucher.usedDate }}</td>
			</tr>
		</table>
	</b-card>
	<b-modal id="modal-new-voucher" title="New voucher" hide-footer>
		<div class="row">
			<div class="col"><label for="cms--voucher-code">Code:</label></div>
			<div class="col text-right">
				<button class="btn btn-secondary btn-sm py-0" @click="generateRandomCode()">Generate random code</button>
			</div>
		</div>
		<b-form-input v-model="form.code" id="cms--voucher-code" />
		<label class="w-100 mt-2">Type:<b-form-select v-model="form.type" :options="types" /></label>
		<label class="w-100">
			Value (ID of product, category or fix sale value):
			<b-form-input v-model="form.value" type="number" />
		</label>
		<label class="w-100">Percentage (0-100):<b-form-input v-model="form.percentage" type="number" /></label>
		<label class="w-100">Usage limit (empty for infinity):<b-form-input v-model="form.usageLimit" type="number" /></label>
		<label class="w-100">Must be unique in one cart?<b-form-checkbox v-model="form.mustBeUnique" /></label>
		<label class="w-100">Internal note:<b-form-textarea v-model="form.note" /></label>
		<label class="w-100">Valid from:<b-form-datepicker v-model="form.validFrom" /></label>
		<label class="w-100">Valid to:<b-form-datepicker v-model="form.validTo" /></label>
	</b-modal>
</div>`,
  data() {
    return {
      feed: null,
      types: [],
      form: {
        code: "",
        type: "",
        value: "",
        percentage: "",
        usageLimit: "",
        mustBeUnique: "",
        note: "",
        validFrom: "",
        validTo: "",
      },
    };
  },
  created() {
    this.sync();
  },
  methods: {
    sync() {
      axiosApi.get("cart-voucher").then((req) => {
        this.feed = req.data.feed;
        this.types = req.data.types;
      });
    },
    generateRandomCode() {
      axiosApi.get("cart-voucher/generate-random-code").then((req) => {
        this.form.code = req.data.code;
      });
    },
  },
});
