// app/layout.js

export default function RootLayout({ children }) {
  return (
    <html lang="fr">
      <head>
        <meta charSet="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>QWAI POD</title>
      </head>
      <body>
        <main>{children}</main>
      </body>
    </html>
  )
}
