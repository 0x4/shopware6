import Vue from 'vue';
import { notification, context, data } from '@shopware-ag/meteor-admin-sdk';

const { repository, Classes: { Criteria } } = data;

export default Vue.extend({
    template: `
        <div>
          <h1>Hello from the new menu page with searchbar</h1>
        </div>
    `,
    methods: {}
})
