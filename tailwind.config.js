module.exports = {
  future: {
    removeDeprecatedGapUtilities: true,
    purgeLayersByDefault: true,
  },
  purge: [
    './src/**/*.html',
  ],
  theme: {
    extend: {
      colors: {
        green: {
          300: "#D9E1B9",
          900: "#a9c23a",
        },
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
      },
      typography: (theme) => ({
        default: {
          css: {
            maxWidth: "none",
            h2: {
              fontSize: '3rem',
              marginBottom: '0.2em'
            },
            h3: {
              fontSize: '1.8rem',
              fontWeight: '400',
              marginBottom: '0.2em'
            },
            ul: {
              fontWeight: '300',
              li: {
                '&::before': {
                  backgroundColor: 'black'
                }
              }
            },
            p: {
              fontWeight: '300',
            },
            a: {
              color: theme('colors.blue.300'),
              '&:hover': {
                color: theme('colors.blue.400'),
              },
            },
          },
        },
      }),
    },
  },
  variants: {},
  plugins: [
    require('@tailwindcss/typography'),
  ],
}
