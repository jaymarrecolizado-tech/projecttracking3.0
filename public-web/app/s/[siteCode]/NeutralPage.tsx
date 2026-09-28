/**
 * The neutral page shown for both non-OK survey states — an unrecognised link
 * and a survey that is not currently open.
 *
 * It mirrors `PublicSurveyLayout.vue` minus the console chrome, and says the
 * minimum. A stranger who mistypes a code learns nothing: not whether the site
 * exists, not how many sites there are.
 */
export function NeutralPage({ title, body }: { title: string; body: string }) {
  return (
    <div className="shell">
      <header className="identity">
        <p className="identity__title">
          <span className="identity__mark" aria-hidden="true" />
          Free WiFi Feedback
        </p>
      </header>

      <main className="main">
        <div className="card">
          <h1 className="card__title">{title}</h1>
          <p className="card__intro">{body}</p>
        </div>
      </main>

      <footer className="footer">
        Your answers are anonymous. No name, email or contact details are collected.
      </footer>
    </div>
  );
}
