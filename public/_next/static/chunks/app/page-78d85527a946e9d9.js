(self.webpackChunk_N_E = self.webpackChunk_N_E || []).push([[8974], {
    36988: (e, n, t) => {
        "use strict";
        t.d(n, { GraphiQLPage: () => graphiQlPage });
        var s = t(95155), a = t(56728);
        async function fecther(payload) {
            return await getGraphQlResponse(payload);
        }
        t(25403), t(1730);
        let graphiQlPage = () => (0, s.jsx)(a.J, { fetcher: fecther })
    },
    49343: (e, n, t) => {
        Promise.resolve().then(t.bind(t, 36988))
    }
}, e => { e.O(0, [2541, 2545, 5940, 1760, 758, 8441, 1255, 7358], () => e(e.s = 49343)), _N_E = e.O() }]);