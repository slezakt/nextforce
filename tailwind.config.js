module.exports = {
  future: {
    // removeDeprecatedGapUtilities: true,
    // purgeLayersByDefault: true,
  },
  purge: [],
  theme: {
    extend: {
      colors: {
        green: "#a9c23a",
        blue: {
          100: "#80E6FF",
          200: "#0CF",
          300: "#08c",
          400: "#005580"
        }
      },
      padding: (theme) => ({
        "2/5": "40%",
      }),
      margin: (theme) => ({
        "2/5": "40%",
      }),
      maxWidth: {
        "200": "200px",
        "700": "700px",
      },
      fontSize: {
        "0": "0",
      },
      fontFamily: {
        "Roboto Condensed": ['"Roboto Condensed"', 'sans-serif']
      }
    },
  },
  variants: {},
  plugins: [],
}
